<script setup lang="ts">
import { ref, watch, computed, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { ImageOff, Plus } from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { useAdvisories, type Advisory } from '@/composables/advisories';

type AdvisoriesPagination = {
  current_page: number
  per_page: number
  total: number
  data: Advisory[]
}

type Option = { value: number | string; name: string }

const page = usePage();
const { canCreate, slug, hasPermission } = useModulePermissions();
const {
  newAdvisory,
  editAdvisory,
  toggleAdvisory,
  deleteAdvisory,
  previewAdvisory,
} = useAdvisories();

const advisories = computed<AdvisoriesPagination>(() => {
  return (page.props as { advisories?: AdvisoriesPagination }).advisories ?? {
    current_page: 1,
    per_page: 10,
    total: 0,
    data: [],
  };
});

const stateOptions = computed<Option[]>(() => (page.props as { advisory_states?: Option[] }).advisory_states ?? []);

const initialQuery = new URLSearchParams((page.url ?? '').split('?')[1] ?? '');
const filters = ref({
  title: initialQuery.get('title') ?? '',
  state: initialQuery.get('state') ?? '',
});
const filtersActive = computed(() => filters.value.title !== '' || filters.value.state !== '');

const hasInitialized = ref(false);
const isUpdatingFromServer = ref(false);
const pagination = ref({
  current_page: advisories.value.current_page,
  per_page: Number(advisories.value.per_page),
  total: advisories.value.total,
});

const stateFilterModel = computed({
  get: () => (filters.value.state === '' ? undefined : filters.value.state),
  set: (v: string | undefined) => {
    filters.value.state = v ?? '';
  },
});

const columnHelper = createColumnHelper<Advisory>();

const baseColumns: any[] = [
  columnHelper.accessor('image_url', {
    header: 'Image',
    cell: (info: any) => {
      const url = info.getValue() as string | null;
      return url
        ? h('img', { src: url, alt: '', class: 'h-12 w-12 rounded-md border object-cover bg-muted', loading: 'lazy' })
        : h('div', { class: 'flex h-12 w-12 items-center justify-center rounded-md border bg-muted text-muted-foreground' }, [h(ImageOff, { class: 'size-4' })]);
    },
  }),
  columnHelper.accessor('title', {
    header: 'Title',
    cell: (info: any) => {
      const row = info.row.original as Advisory;
      return h('div', { class: 'flex flex-col' }, [
        h('span', { class: 'font-medium' }, String(info.getValue() ?? '')),
        row.deleted_at ? h('span', { class: 'text-xs text-red-500' }, 'Deleted') : null,
      ]);
    },
  }),
  columnHelper.display({
    id: 'window',
    header: 'Shown',
    cell: ({ row }: any) => {
      const r = row.original as Advisory;
      return h('div', { class: 'flex flex-col text-xs' }, [
        h('span', null, `From: ${r.starts_at ?? 'Immediately'}`),
        h('span', { class: 'text-muted-foreground' }, `Until: ${r.ends_at ?? 'Disabled manually'}`),
      ]);
    },
  }),
  columnHelper.accessor('state', {
    header: 'Status',
    cell: ({ row, getValue }: any) => {
      const r = row.original as Advisory;
      return h('span', { class: [r.state_color ?? '', 'px-2 py-1 rounded-md border text-xs font-medium'] }, String(getValue() ?? ''));
    },
  }),
  columnHelper.accessor('updated_by', {
    header: 'Updated By',
    cell: (info: any) => info.getValue() ?? '—',
  }),
  columnHelper.accessor('updated_at', {
    header: 'Updated At',
    cell: (info: any) => info.getValue(),
  }),
];

const handlerMap: Record<string, (row: Advisory) => void> = {
  edit: editAdvisory,
  toggle_active: toggleAdvisory,
  destroy: deleteAdvisory,
};

/** Per-row label and icon for actions whose meaning flips with the row's state. */
const dynamicPropsMap: Record<string, (row: Advisory) => { name: string; icon: string }> = {
  toggle_active: (row) => Number(row.is_active) !== 0
    ? { name: 'Disable Advisory', icon: 'ToggleRight' }
    : { name: 'Enable Advisory', icon: 'ToggleLeft' },
  destroy: (row) => row.deleted_at
    ? { name: 'Restore Advisory', icon: 'RotateCcw' }
    : { name: 'Delete Advisory', icon: 'Trash2' },
};

const columns = computed(() => {
  const rawModules = (page.props as { sub_modules?: { slug: string }[] }).sub_modules ?? [];
  const subModules = rawModules
    .filter((m) => hasPermission(m.slug) && handlerMap[m.slug.split('.')[1]])
    .map((m) => {
      const action = m.slug.split('.')[1];
      return {
        ...m,
        handler: handlerMap[action],
        dynamicProps: dynamicPropsMap[action],
        // A deleted advisory can only be restored.
        shouldRender: (row: Advisory) => action === 'destroy' || !row.deleted_at,
      };
    });

  return subModules.length
    ? [...baseColumns, createActionColumn(subModules as any)]
    : baseColumns;
});

const listFetchPath = computed(() => {
  const raw = (page.url ?? '').split('?')[0] || '';
  return raw.startsWith('/') ? raw : `/${slug.value}`;
});

const fetchAdvisories = () => {
  const params: Record<string, string | number> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
  };
  if (filters.value.title) params.title = filters.value.title;
  if (filters.value.state) params.state = filters.value.state;

  router.get(listFetchPath.value, params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['advisories'],
  });
};

const clearFilters = () => {
  filters.value = { title: '', state: '' };
};

const filterWatchTimeout = ref<number | null>(null);
watch(
  filters,
  () => {
    hasInitialized.value = true;
    pagination.value.current_page = 1;
    if (filterWatchTimeout.value) clearTimeout(filterWatchTimeout.value);
    filterWatchTimeout.value = window.setTimeout(fetchAdvisories, 500);
  },
  { deep: true },
);

watch(advisories, (next) => {
  if (!next) return;
  isUpdatingFromServer.value = true;
  pagination.value.current_page = next.current_page;
  pagination.value.per_page = Number(next.per_page);
  pagination.value.total = next.total;
  setTimeout(() => {
    isUpdatingFromServer.value = false;
  }, 100);
});

const paginationWatchTimeout = ref<number | null>(null);
watch(
  () => [pagination.value.current_page, pagination.value.per_page],
  ([currentPage, perPage]) => {
    if (!hasInitialized.value || isUpdatingFromServer.value) return;
    if (paginationWatchTimeout.value) clearTimeout(paginationWatchTimeout.value);
    paginationWatchTimeout.value = window.setTimeout(() => {
      pagination.value.current_page = Number(currentPage) || 1;
      pagination.value.per_page = Number(perPage) || 10;
      fetchAdvisories();
    }, 50);
  },
);

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Advisories',
    href: `/${slug.value}`,
  },
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Advisories" />

    <div class="flex flex-1 flex-col gap-4 p-4">
      <Card class="gap-0 py-0">
        <CardHeader class="flex flex-col gap-4 border-b px-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6 [.border-b]:pb-5">
          <div class="min-w-0 space-y-1.5">
            <h1 class="text-lg leading-none font-semibold tracking-tight">Advisories</h1>
            <CardDescription>
              Notices shown as a pop-up to every user right after they sign in, while enabled and within their schedule.
            </CardDescription>
          </div>
          <Button v-if="canCreate" class="cursor-pointer" @click="newAdvisory">
            <Plus /> New Advisory
          </Button>
        </CardHeader>

        <div class="flex flex-col gap-3 border-b px-4 py-4 sm:flex-row sm:items-end sm:px-6">
          <div class="grid gap-2 sm:w-72">
            <Label for="advisory-filter-title">Title</Label>
            <Input
              id="advisory-filter-title"
              v-model="filters.title"
              type="text"
              autocomplete="off"
              placeholder="Search title"
            />
          </div>
          <div class="grid gap-2 sm:w-48">
            <Label for="advisory-filter-state">Status</Label>
            <Select v-model="stateFilterModel">
              <SelectTrigger id="advisory-filter-state" class="w-full">
                <SelectValue placeholder="All statuses" />
              </SelectTrigger>
              <SelectContent class="w-full">
                <SelectGroup>
                  <SelectLabel>Status</SelectLabel>
                  <SelectItem
                    v-for="opt in stateOptions"
                    :key="String(opt.value)"
                    :value="String(opt.value)"
                  >
                    {{ opt.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>
          <Button v-if="filtersActive" variant="outline" class="cursor-pointer" @click="clearFilters">
            Clear filters
          </Button>
        </div>

        <div class="px-4 py-4 sm:px-6">
          <Datatable
            :data="advisories.data"
            :enable-row-click="true"
            :enable-search="false"
            :row-click="previewAdvisory"
            :columns="columns"
            :pagination="pagination"
            :search-fields="[]"
            empty-message="No advisories found"
            empty-description="Create an advisory to show a notice to users after they sign in."
            @update:pagination="(newPagination) => { hasInitialized = true; pagination = newPagination }"
          />
        </div>
      </Card>
    </div>
  </AppLayout>
</template>
