<script setup lang="ts">
import { ref, watch, computed, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { Auth, User, UserDetail, type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { useConcerns } from '@/composables/concerns';
import RightPane from '@/components/RightPane.vue';
import {
  emptyConcernListFilters,
  concernListFiltersToParams,
  concernListFiltersActive,
  concernListFiltersFromUrlQuery,
  type ConcernListFilters,
  type ConcernListOption,
} from '@/composables/concernListFilters';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion'

type ConcernsPagination = {
  current_page: number
  per_page: number
  total: number
  data: unknown[]
}

const page = usePage();
const auth = computed(() => (page.props as any).auth as Auth);
const user = computed(() => auth.value?.user as User);
const userDetail = computed(() => user.value?.user_detail as UserDetail);
const { canCreate, slug, hasPermission } = useModulePermissions();
const {
  newConcern,
  viewConcern,
  editConcern,
  deleteConcern,
  previewFile,
  closePane,
  rightPaneVisible,
  rightPaneTitle,
  rightPaneLoading,
  rightPaneError,
  rightPaneContentComponent,
  rightPaneComponentProps,
 } = useConcerns();

const concernsFromProps = computed(() => {
  const propsConcerns = (page.props as any).concerns as ConcernsPagination | undefined;
  if (!propsConcerns) {
    return {
      current_page: 1,
      per_page: 10,
      total: 0,
      data: [],
    } as ConcernsPagination;
  }
  return propsConcerns;
});

const concerns = computed(() => concernsFromProps.value);
const filters = ref<ConcernListFilters>(concernListFiltersFromUrlQuery(page.url));
const hasInitialized = ref(false);
const isUpdatingFromServer = ref(false);
const columnHelper = createColumnHelper();
const pagination = ref({
  current_page: concerns.value.current_page,
  per_page: Number(concerns.value.per_page),
  total: concerns.value.total,
});

const concernTypeOptions = computed<ConcernListOption[]>(() => {
  return (page.props as { concern_types?: ConcernListOption[] }).concern_types ?? [];
});

const statusOptions = computed<ConcernListOption[]>(() => {
  return (page.props as { ticket_statuses?: ConcernListOption[] }).ticket_statuses ?? [];
});

const listFetchPath = computed(() => {
  const raw = (page.url ?? '').split('?')[0] || '';
  return raw.startsWith('/') ? raw : `/${slug.value}`;
});

const partialReloadKey = computed(() => slug.value || 'concerns');

const concernTypeModel = computed({
  get: () => (filters.value.type === '' ? undefined : filters.value.type),
  set: (v: string | undefined) => {
    filters.value.type = v ?? '';
  },
});

const statusFilterModel = computed({
  get: () => (filters.value.status === '' ? undefined : filters.value.status),
  set: (v: string | undefined) => {
    filters.value.status = v ?? '';
  },
});

const baseColumns: any[] = [
  columnHelper.accessor('title', {
    header: 'Title',
    cell: (info: any) => info.getValue(),
  }),
  columnHelper.accessor('type', {
    header: 'Type',
    cell: (info: any) => info.getValue(),
  }),
  columnHelper.accessor('status', {
    header: 'Status',

    cell: ({ row, getValue }: any) => {
      const r = row.original as { status?: string; status_color?: string }
      return h(
        'span',
        {
          class: [
            r.status_color ?? '',
            'px-2 py-1 rounded-md font-medium',
          ],
        },
        String(getValue() ?? ''),
      )
    },
  }),
  columnHelper.accessor('created_by', {
    header: 'Created By',
    cell: (info: any) => info.getValue(),
  }),
];
const handlerMap: Record<string, (row: any) => void> = {
  edit: editConcern,
  update: editConcern,
  delete: deleteConcern,
  destroy: deleteConcern,
  preview_file: previewFile,
}

const columns = computed(() => {
  const rawModules = (page.props as { sub_modules?: { slug: string }[] }).sub_modules ?? []
  const subModules = rawModules
    .filter((m: { slug: string }) => hasPermission(m.slug)
      && m.slug.split('.')[1] != 'create'
      && m.slug.split('.')[1] != 'file_list'
    )
    .map((m: { slug: string }) => ({
      ...m,
      handler: handlerMap[m.slug.split('.')[1]],
    }))

  return subModules.length
    ? [...baseColumns, createActionColumn(subModules as any)]
    : baseColumns
})

const fetchConcerns = () => {
  const params: Record<string, string | number> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
    ...concernListFiltersToParams(filters.value),
  };

  router.get(listFetchPath.value, params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: [partialReloadKey.value],
  });
};

const markInteracted = () => {
  hasInitialized.value = true;
};

const filtersActive = computed(() => concernListFiltersActive(filters.value));

const clearFilters = () => {
  filters.value = emptyConcernListFilters();
  markInteracted();
  pagination.value.current_page = 1;
  fetchConcerns();
};

watch(
  filters,
  () => {
    hasInitialized.value = true;
    pagination.value.current_page = 1;
    if (filterWatchTimeout.value) {
      clearTimeout(filterWatchTimeout.value);
    }
    filterWatchTimeout.value = window.setTimeout(() => {
      fetchConcerns();
    }, 500);
  },
  { deep: true }
);

watch(
  concernsFromProps,
  (next: ConcernsPagination) => {
    if (!next) return;
    isUpdatingFromServer.value = true;
    pagination.value.current_page = next.current_page;
    pagination.value.per_page = Number(next.per_page);
    pagination.value.total = next.total;
    setTimeout(() => {
      isUpdatingFromServer.value = false;
    }, 100);
  },
);

const filterWatchTimeout = ref<number | null>(null);
const paginationWatchTimeout = ref<number | null>(null);
watch(
  () => [pagination.value.current_page, pagination.value.per_page],
  ([currentPage, perPage]) => {
    if (!hasInitialized.value || isUpdatingFromServer.value) return;
    if (paginationWatchTimeout.value) {
      clearTimeout(paginationWatchTimeout.value);
    }
    paginationWatchTimeout.value = window.setTimeout(() => {
      pagination.value.current_page = Number(currentPage) || 1;
      pagination.value.per_page = Number(perPage) || 10;
      fetchConcerns();
    }, 50);
  },
);

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Concerns',
    href: slug.value,
  },
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Concerns" />

    <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
      <div class="flex flex-col gap-4 mb-4">
        <div class="flex flex-row lg:flex-row justify-between items-stretch lg:items-start gap-4">
          <div class="flex flex-1 flex-row gap-3 min-w-0">
            <Button class="cursor-pointer" v-if="canCreate" :onClick="newConcern">Submit Concern</Button>
          </div>
        </div>
        <div v-if="userDetail?.employee_no || auth?.is_superadmin" class="grid gap-2 md:col-span-1 w-1/2">
          <Accordion type="single" collapsible>
            <AccordionItem value="filters">
              <AccordionTrigger class="cursor-pointer">Filters</AccordionTrigger>
              <AccordionContent>
                <div class="flex flex-row lg:flex-row justify-between items-stretch lg:items-start gap-4">
                  <div class="flex flex-1 flex-col gap-3 min-w-0">
                    <div class="grid gap-2 md:col-span-1">
                      <Label for="concern-filter-title">Title</Label>
                      <Input
                        id="concern-filter-title"
                        v-model="filters.title"
                        type="text"
                        autocomplete="off"
                        placeholder="Title"
                        class="mt-0"
                      />
                    </div>
                    <div class="grid gap-2 md:col-span-1">
                      <Label for="concern-filter-description">Description</Label>
                      <Input
                        id="concern-filter-description"
                        v-model="filters.description"
                        type="text"
                        autocomplete="off"
                        placeholder="Description"
                        class="mt-0"
                      />
                    </div>
                    <div class="grid gap-2 md:col-span-1">
                      <Label for="concern-filter-type">Type</Label>
                      <Select v-model="concernTypeModel">
                        <SelectTrigger id="concern-filter-type" class="w-full">
                          <SelectValue placeholder="All types" />
                        </SelectTrigger>
                        <SelectContent class="w-full">
                          <SelectGroup>
                            <SelectLabel>Type</SelectLabel>
                            <SelectItem
                              v-for="opt in concernTypeOptions"
                              :key="String(opt.value)"
                              :value="String(opt.value)"
                            >
                              {{ opt.name }}
                            </SelectItem>
                          </SelectGroup>
                        </SelectContent>
                      </Select>
                    </div>
                    <div class="grid gap-2 md:col-span-1">
                      <Label for="concern-filter-status">Status</Label>
                      <Select v-model="statusFilterModel">
                        <SelectTrigger id="concern-filter-status" class="w-full">
                          <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent class="w-full">
                          <SelectGroup>
                            <SelectLabel>Status</SelectLabel>
                            <SelectItem
                              v-for="opt in statusOptions"
                              :key="String(opt.value)"
                              :value="String(opt.value)"
                            >
                              {{ opt.name }}
                            </SelectItem>
                          </SelectGroup>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 mt-3">
                  <Button
                    v-if="filtersActive"
                    variant="outline"
                    :onClick="clearFilters"
                  >
                    Clear filters
                  </Button>
                </div>
              </AccordionContent>
            </AccordionItem>
          </Accordion>
        </div>
        <div v-else class="w-md">
          <div class="grid gap-2 md:col-span-1">
            <Label for="concern-filter-title">Title</Label>
            <Input
              id="concern-filter-title"
              v-model="filters.title"
              type="text"
              autocomplete="off"
              placeholder="Title"
              class="mt-0"
            />
          </div>
        </div>
      </div>
      <Datatable
        :data="concerns.data"
        :enable-row-click="true"
        :enable-search="false"
        :row-click="viewConcern"
        :columns="columns"
        :pagination="pagination"
        :search-fields="[]"
        empty-message="No concerns found"
        empty-description="Try adjusting filters or pagination settings."
        @update:pagination="(newPagination) => { markInteracted(); pagination = newPagination }"
      />
    </div>
    <RightPane
      :open="rightPaneVisible"
      :title="rightPaneTitle"
      :loading="rightPaneLoading"
      :error="rightPaneError"
      :content-component="rightPaneContentComponent"
      :component-props="rightPaneComponentProps"
      @update:open="(v) => { if (!v && !rightPaneLoading) closePane('right') }"
    />
  </AppLayout>
</template>
