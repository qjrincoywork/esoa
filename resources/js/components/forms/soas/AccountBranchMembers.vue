<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import Datatable from '@/components/Datatable.vue';
import { useAjax } from '@/composables/useAjax';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useSoas } from '@/composables/soas';
import { usePage } from '@inertiajs/vue3';

type MemberDetail = {
  id?: number;
  claimnum?: string;
  policynum?: string;
  firstname?: string;
  lastname?: string;
  middlename?: string;
  suffix?: string;
  event?: string;
};

type MemberSearchField = 'policynum' | 'firstname' | 'lastname';
type SoaActionPayload = {
  id?: number;
  soa_number?: string;
  billing_ref?: string;
};

const props = defineProps<{
  account_code?: string | null;
  branch_code?: string | null;
  soa?: SoaActionPayload | null;
}>();

const { get } = useAjax();
const page = usePage();
const { slug, hasPermission } = useModulePermissions();
const { fileList } = useSoas();
const loading = ref(false);
const members = ref<MemberDetail[]>([]);
const error = ref('');
const fetchToken = ref(0);
const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
});
const searchField = ref<MemberSearchField>('policynum');
const searchText = ref('');
const filtersActive = computed(() => searchText.value.trim().length > 0);
const filterDebounceMs = 500;
const filterWatchTimeout = ref<number | null>(null);

watch(
  [searchField, searchText],
  () => {
    if (filterWatchTimeout.value) {
      clearTimeout(filterWatchTimeout.value);
    }
    filterWatchTimeout.value = window.setTimeout(() => {
      pagination.value.current_page = 1;
      fetchMembers();
    }, filterDebounceMs);
  },
  { deep: true }
);

const columnHelper = createColumnHelper<MemberDetail>();
const baseColumns = [
  // columnHelper.accessor('claimnum', {
  //   id: 'claimnum',
  //   header: 'Claim Number',
  //   cell: ({ row, getValue }) => getValue() || row.original.claimnum || '—',
  // }),
  columnHelper.accessor('policynum', {
    id: 'policynum',
    header: 'Policy Number',
    cell: ({ row, getValue }) => getValue() || '—',
  }),
  columnHelper.accessor('lastname', {
    id: 'lastname',
    header: 'Last Name',
    cell: ({ row, getValue }) => getValue() || '—',
  }),
  columnHelper.accessor('firstname', {
    id: 'firstname',
    header: 'First Name',
    cell: ({ row, getValue }) => getValue() || '—',
  }),
  columnHelper.accessor('middlename', {
    id: 'middlename',
    header: 'Middle Name',
    cell: ({ row, getValue }) => getValue() || '—',
  }),
];

const handlerMap: Record<string, () => void> = {
  file_list: () => {
    if (!props.soa) return;
    fileList(props.soa);
  },
};

const columns = computed(() => {
  const rawModules = (page.props as { sub_modules?: { slug: string }[] }).sub_modules ?? [];
  const subModules = rawModules
    .filter((m) => hasPermission(m.slug))
    .filter((m) => m.slug.split('.')[1] === 'file_list')
    .map((m) => ({
      ...m,
      handler: handlerMap[m.slug.split('.')[1]] ?? (() => undefined),
    }));

  return subModules.length ? [...baseColumns, createActionColumn(subModules as any)] : baseColumns;
});

const searchOptions: { label: string; value: MemberSearchField }[] = [
  { label: 'Policy Number', value: 'policynum' },
  { label: 'First Name', value: 'firstname' },
  { label: 'Last Name', value: 'lastname' },
];

const clearFilters = () => {
  if (filterWatchTimeout.value) {
    clearTimeout(filterWatchTimeout.value);
    filterWatchTimeout.value = null;
  }
  searchField.value = 'policynum';
  searchText.value = '';
  pagination.value.current_page = 1;
  fetchMembers();
};

const fetchMembers = async () => {
  if (!props.account_code || !props.branch_code) {
    members.value = [];
    pagination.value.total = 0;
    return;
  }
  const token = ++fetchToken.value;
  loading.value = true;
  error.value = '';

  const params: Record<string, string | number> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
  };

  const term = searchText.value.trim();
  if (term) {
    params[searchField.value] = term;
  }

  try {
    const response = await get<{
      members?: {
        data?: MemberDetail[];
        current_page?: number;
        per_page?: number;
        total?: number;
      };
    }>(`/${slug.value}/${props.account_code}/${props.branch_code}/members`, params);

    if (!response.ok) {
      throw new Error('Failed to load members');
    }
    if (token !== fetchToken.value) return;

    const payload = response.data?.members;
    members.value = [...(payload?.data ?? [])];
    pagination.value.current_page = Number(payload?.current_page ?? 1);
    pagination.value.per_page = Number(payload?.per_page ?? 10);
    pagination.value.total = Number(payload?.total ?? 0);
  } catch {
    if (token !== fetchToken.value) return;
    error.value = 'Unable to load account / branch members.';
    members.value = [];
    pagination.value.total = 0;
  } finally {
    if (token === fetchToken.value) {
      loading.value = false;
    }
  }
};

const handlePaginationUpdate = (newPagination: {
  current_page: number;
  per_page: number;
  total: number;
}) => {
  pagination.value = {
    ...pagination.value,
    ...newPagination,
  };
  fetchMembers();
};

watch(
  () => [props.account_code, props.branch_code],
  () => {
    pagination.value.current_page = 1;
    fetchMembers();
  },
  { immediate: true }
);

onMounted(fetchMembers);
</script>

<template>
  <div class="bg-[var(--color-surface)] rounded-md shadow-sm border border-[var(--color-border)] p-6">
    <div class="flex flex-col gap-4 mb-4">
      <div class="grid gap-2 md:grid-cols-[220px_1fr] items-end">
        <div class="grid gap-2">
          <Label for="account-branch-members-search-field">Search Field</Label>
          <Select :model-value="searchField" @update:model-value="(value) => searchField = (value as MemberSearchField)">
            <SelectTrigger id="account-branch-members-search-field" class="w-full">
              <SelectValue placeholder="Select field" />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup>
                <SelectLabel>Search by</SelectLabel>
                <SelectItem v-for="option in searchOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>
        <div class="grid gap-2">
          <Label for="account-branch-members-search-text">Search</Label>
        <Input
            id="account-branch-members-search-text"
            v-model="searchText"
          type="text"
          autocomplete="off"
            :placeholder="`Enter ${searchOptions.find((option) => option.value === searchField)?.label ?? 'keyword'}`"
          class="mt-0"
        />
        </div>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <Button
          v-if="filtersActive"
          variant="outline"
          :onClick="clearFilters">
          Clear filters
      </Button>
    </div>
  </div>
  <Datatable
    :key="`${pagination.current_page}-${pagination.per_page}-${members.length}`"
    :data="members"
    :columns="columns"
    :pagination="pagination"
    :enable-search="false"
    :search-fields="[]"
    :loading="loading"
    :error="error"
    empty-message="No members found"
    empty-description="Members for this account / branch will appear here."
    export-file-name="members"
    @update:pagination="handlePaginationUpdate" />
</template>
