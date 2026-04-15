<script setup lang="ts">
import { onMounted, ref, watch, computed, nextTick } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import Datatable from '@/components/Datatable.vue';
import { useAjax } from '@/composables/useAjax';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useModulePermissions } from '@/composables/useModulePermissions';
import {
  emptyMemberListFilters,
  memberListFiltersToParams,
  memberListFiltersActive,
  memberListFiltersFromUrlQuery,
  type MemberListFilters,
  type MemberListOption,
} from '@/composables/memberListFilters';
import { router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

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

const props = defineProps<{
  account_code?: string | null;
  branch_code?: string | null;
}>();

const { get } = useAjax();
const page = usePage();
const { slug } = useModulePermissions();
const loading = ref(false);
const members = ref<MemberDetail[]>([]);
const error = ref('');
const fetchToken = ref(0);
const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
});
const markInteracted = () => {
  hasInitialized.value = true;
};
const suppressFilterWatch = ref(false);
const filtersBootstrapped = ref(false);

const clearFilters = () => {
  if (filterWatchTimeout.value) {
    clearTimeout(filterWatchTimeout.value);
    filterWatchTimeout.value = null;
  }
  suppressFilterWatch.value = true;
  filters.value = emptyMemberListFilters();
  pagination.value.current_page = 1;
  fetchMembers();
  nextTick(() => {
    suppressFilterWatch.value = false;
  });
};
const hasInitialized = ref(false);

const filtersActive = computed(() => memberListFiltersActive(filters.value));
const filters = ref<MemberListFilters>(memberListFiltersFromUrlQuery(page.url));
const filterDebounceMs = 500;
const filterWatchTimeout = ref<number | null>(null);

watch(
  filters,
  () => {
    if (suppressFilterWatch.value) return;
    hasInitialized.value = true;
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
const columns = [
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
];

const fetchMembers = () => {
  const params: Record<string, string | number> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
    ...memberListFiltersToParams(filters.value),
  };

  router.get(
    `/${slug.value}/${props.account_code}/${props.branch_code}/members`,
    params,
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: [`${slug.value}/${props.account_code}/${props.branch_code}/members`]
    }
  );
};

onMounted(async () => {
  await nextTick();
  filtersBootstrapped.value = true;
});
</script>

<template>
  <div class="bg-[var(--color-surface)] rounded-md shadow-sm border border-[var(--color-border)] p-6">
    <div class="flex flex-col gap-4 mb-4">
      <div class="grid gap-2 md:col-span-1">
        <Label for="account-branch-members-filter-policynum">Policy Number</Label>
        <Input
          id="account-branch-members-filter-policynum"
          v-model="filters.policynum"
          type="text"
          autocomplete="off"
          placeholder="Policy Number"
          class="mt-0"
        />
        <Label for="account-branch-members-filter-lastname">Last Name</Label>
        <Input
          id="account-branch-members-filter-lastname"
          v-model="filters.lastname"
          type="text"
          autocomplete="off"
          placeholder="Last Name"
        />
        <Label for="account-branch-members-filter-firstname">First Name</Label>
        <Input
          id="account-branch-members-filter-firstname"
          v-model="filters.firstname"
          type="text"
          autocomplete="off"
          placeholder="First Name"
        />
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
    @update:pagination="(newPagination) => { markInteracted(); pagination = newPagination }" />
</template>
