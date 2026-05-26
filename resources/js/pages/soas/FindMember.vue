<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import TopPane from '@/components/TopPane.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAjax } from '@/composables/useAjax';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { useSoas } from '@/composables/soas';
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import SoaFileBrowser from '@/components/forms/soas/SoaFileBrowser.vue';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import type { BreadcrumbItem } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

type Member = {
  id?: number;
  policynum?: string;
  firstname?: string;
  lastname?: string;
  middlename?: string;
  suffix?: string;
  account_code?: string;
  company_name?: string;
  claimnum?: string;
  batch_number?: string;
};

type FilterKey = 'policynum' | 'batch_number' | 'lastname' | 'firstname' | 'account_code' | 'company_name';
type Filters = Record<FilterKey, string>;

// ─── Constants ───────────────────────────────────────────────────────────────

const DEBOUNCE_MS = 2000;

const FILTER_KEYS: FilterKey[] = [
  'policynum',
  'batch_number',
  'lastname',
  'firstname',
  'account_code',
  'company_name',
];

const emptyFilters = (): Filters => ({
  policynum: '',
  batch_number: '',
  lastname: '',
  firstname: '',
  account_code: '',
  company_name: '',
});

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Find Member', href: '/soas/find_member' },
];

// ─── Composables ─────────────────────────────────────────────────────────────

const { get } = useAjax();
const { hasPermission } = useModulePermissions();
const {
  openPane,
  closePane,
  topPaneVisible,
  topPaneTitle,
  topPaneLoading,
  topPaneError,
  topPaneContentComponent,
  topPaneComponentProps,
} = useSoas();

// ─── State ───────────────────────────────────────────────────────────────────

const loading    = ref(false);
const error      = ref('');
const members    = ref<Member[]>([]);
const filters    = ref<Filters>(emptyFilters());
const pagination = ref({ current_page: 1, per_page: 10, total: 0 });
const hasSearched = ref(false);

// One timer per field so each field's debounce runs independently.
const debounceTimers = Object.fromEntries(
  FILTER_KEYS.map(k => [k, null as ReturnType<typeof setTimeout> | null])
) as Record<FilterKey, ReturnType<typeof setTimeout> | null>;

// ─── Computed ────────────────────────────────────────────────────────────────

const filtersActive = computed(() =>
  FILTER_KEYS.some(k => filters.value[k].trim() !== '')
);

const canViewMemberFiles = computed(() => hasPermission('soas.member_files'));

// ─── Table columns ───────────────────────────────────────────────────────────

const columnHelper = createColumnHelper<Member>();

const baseColumns = [
  columnHelper.accessor('policynum',    { header: 'Policy Number', cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('batch_number', { header: 'Batch Number',  cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('claimnum',     { header: 'Claim Number',  cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('lastname',     { header: 'Last Name',     cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('firstname',    { header: 'First Name',    cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('middlename',   { header: 'Middle Name',   cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('account_code', { header: 'Account Code',  cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('company_name', { header: 'Company Name',  cell: ({ getValue }) => getValue() || '—' }),
];

const columns = computed(() => {
  if (!canViewMemberFiles.value) return baseColumns;

  return [
    ...baseColumns,
    createActionColumn([
      {
        slug: 'soas.member_files',
        name: 'View Attachments',
        icon: 'FolderOpen',
        color: 'blue',
        handler: (item: Member) => openMemberFiles(item),
      },
    ] as any),
  ];
});

// ─── Data fetching ───────────────────────────────────────────────────────────

const fetchMembers = async () => {
  if (!filtersActive.value) {
    members.value = [];
    pagination.value.total = 0;
    hasSearched.value = false;
    return;
  }

  showLoader();
  loading.value = true;
  error.value = '';
  hasSearched.value = true;

  try {
    const params: Record<string, string | number> = {
      page:     pagination.value.current_page,
      per_page: pagination.value.per_page,
    };

    FILTER_KEYS.forEach(key => {
      const val = filters.value[key].trim();
      if (val) params[key] = val;
    });

    const response = await get<{
      data: Member[];
      current_page: number;
      last_page: number;
      total: number;
      per_page: number;
    }>('/soas/find_member', params);

    if (!response.ok) throw new Error('Failed to fetch members');

    const payload      = response.data!;
    members.value      = payload.data;
    pagination.value   = {
      current_page: payload.current_page,
      per_page:     payload.per_page,
      total:        payload.total,
    };
  } catch {
    error.value        = 'Unable to load members. Please try again.';
    members.value      = [];
    pagination.value.total = 0;
  } finally {
    hideLoader();
    loading.value = false;
  }
};

// Per-field debounce: each field gets its own independent timer so typing in
// one field does not reset the countdown for another field already in flight.
FILTER_KEYS.forEach(key => {
  watch(
    () => filters.value[key],
    () => {
      if (debounceTimers[key]) clearTimeout(debounceTimers[key]!);
      debounceTimers[key] = setTimeout(() => {
        pagination.value.current_page = 1;
        fetchMembers();
      }, DEBOUNCE_MS);
    }
  );
});

// ─── File attachment viewer ───────────────────────────────────────────────────

const openMemberFiles = async (member: Member) => {
  if (!member.claimnum) {
    dispatchNotification({ title: 'Info', content: 'No claim number for this record.', type: 'info' });
    return;
  }

  showLoader();
  try {
    const response = await get<{ files: { name: string; preview_token: string }[] }>(
      '/soas/member_files',
      { claimnum: member.claimnum }
    );

    if (!response.ok) throw new Error('Failed to fetch files');

    openPane({
      title:          `Attachments — ${member.claimnum}`,
      side:           'top',
      component:      SoaFileBrowser,
      componentProps: { files: response.data?.files ?? [] },
    });
  } catch {
    dispatchNotification({ title: 'Error', content: 'Unable to load attachments.', type: 'error' });
  } finally {
    hideLoader();
  }
};

// ─── Helpers ─────────────────────────────────────────────────────────────────

const clearFilters = () => {
  FILTER_KEYS.forEach(key => {
    if (debounceTimers[key]) {
      clearTimeout(debounceTimers[key]!);
      debounceTimers[key] = null;
    }
  });
  filters.value     = emptyFilters();
  members.value     = [];
  pagination.value  = { current_page: 1, per_page: 10, total: 0 };
  hasSearched.value = false;
  error.value       = '';
};

const handlePaginationUpdate = (newPagination: { current_page: number; per_page: number; total: number }) => {
  pagination.value = { ...pagination.value, ...newPagination };
  fetchMembers();
};
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Find Member" />

    <div class="flex flex-col gap-6 p-4">
      <!-- Search filters -->
      <div class="bg-[var(--color-surface)] rounded-md shadow-sm border border-[var(--color-border)] p-6">
        <h2 class="text-base font-semibold mb-4">Search Member</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div class="grid gap-1.5">
            <Label for="fm-policynum">Policy Number</Label>
            <Input id="fm-policynum" v-model="filters.policynum" placeholder="e.g. TP-12345678-12345-12" autocomplete="off" />
          </div>

          <div class="grid gap-1.5">
            <Label for="fm-lastname">Last Name</Label>
            <Input id="fm-lastname" v-model="filters.lastname" placeholder="e.g. Dela Cruz" autocomplete="off" />
          </div>

          <div class="grid gap-1.5">
            <Label for="fm-firstname">First Name</Label>
            <Input id="fm-firstname" v-model="filters.firstname" placeholder="e.g. Juan" autocomplete="off" />
          </div>

          <div class="grid gap-1.5">
            <Label for="fm-batch">Batch Number</Label>
            <Input id="fm-batch" v-model="filters.batch_number" placeholder="e.g. EO-1234567" autocomplete="off" />
          </div>

          <div class="grid gap-1.5">
            <Label for="fm-account-code">Account Code</Label>
            <Input id="fm-account-code" v-model="filters.account_code" placeholder="e.g. TP-12345678 / AT-12345678" autocomplete="off" />
          </div>

          <div class="grid gap-1.5">
            <Label for="fm-company">Company Name</Label>
            <Input id="fm-company" v-model="filters.company_name" placeholder="e.g. Juan Dela Cruz Corp" autocomplete="off" />
          </div>
        </div>

        <div class="mt-4 flex items-center gap-2">
          <Button v-if="filtersActive" variant="outline" @click="clearFilters">
            Clear filters
          </Button>
          <p v-if="!filtersActive" class="text-sm text-muted-foreground">
            Enter at least one search term to find members.
          </p>
        </div>
      </div>

      <!-- Results datatable -->
      <Datatable
        :key="`${pagination.current_page}-${pagination.per_page}-${members.length}`"
        :data="members"
        :columns="columns"
        :pagination="pagination"
        :enable-search="false"
        :search-fields="[]"
        :loading="loading"
        :error="error"
        :empty-message="hasSearched ? 'No members found' : 'Search to view results'"
        :empty-description="hasSearched ? 'Try adjusting your search terms.' : 'Use the filters above to search for members.'"
        export-file-name="members"
        @update:pagination="handlePaginationUpdate"
      />
    </div>

    <!-- Top pane: RM attachment viewer -->
    <TopPane
      :open="topPaneVisible"
      :title="topPaneTitle"
      :side="'top'"
      :loading="topPaneLoading"
      :error="topPaneError"
      :content-component="topPaneContentComponent"
      :component-props="topPaneComponentProps"
      @update:open="(v) => { if (!v && !topPaneLoading) closePane('top') }"
    />
  </AppLayout>
</template>
