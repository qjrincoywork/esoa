<script setup lang="ts">
import { ref, watch, computed, h, nextTick, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';
import { SearchableCombobox, type SearchableComboboxItem } from '@/components/ui/searchable-combobox';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useSoas } from '@/composables/soas';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { debounce } from '@/composables/utilities/helper';
import RightPane from '@/components/RightPane.vue';
import {
  emptySoaListFilters,
  soaListFiltersToParams,
  soaListFiltersActive,
  soaListFiltersFromUrlQuery,
  type SoaListFilters,
  type SoaListOption,
} from '@/composables/soaListFilters';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion';

type SoasPagination = {
  current_page: number
  per_page: number
  total: number
  data: unknown[]
}

const page = usePage();
const { canCreate, slug, hasPermission } = useModulePermissions();

const {
  newSoa,
  fileList,
  editSoa,
  deleteSoa,
  viewSoa,
  manageFile,
  untagSoa,
  openSoaFilesPane,
  getAccountsByParams,
  getBranchesByParams,
  getBillingRefsByParams,
  rightPaneVisible,
  rightPaneTitle,
  rightPaneLoading,
  rightPaneError,
  rightPaneContentComponent,
  rightPaneComponentProps,
  closeRightPane,
  soaListRowPatches,
  clearSoaListRowPatches,
} = useSoas();

/** Inertia payload only — avoids treating client row patches as server updates. */
const soasFromProps = computed(() => {
  const propsSoas = (page.props as any).soas as SoasPagination | undefined;
  if (!propsSoas) {
    return {
      current_page: 1,
      per_page: 10,
      total: 0,
      data: [],
    } as SoasPagination;
  }
  return propsSoas;
});

/** Table rows merged with `soaListRowPatches` (e.g. amount after adjust in the right pane). */
const soas = computed(() => {
  const base = soasFromProps.value;
  const patches = soaListRowPatches.value;
  const raw = (base.data ?? []) as Record<string, unknown>[];
  const data = raw.map((row) => {
    const id = row.id;
    if (id == null) return row;
    const numId = typeof id === 'number' ? id : Number(id);
    if (Number.isNaN(numId)) return row;
    const p = patches[numId];
    return p ? { ...row, ...p } : row;
  });
  return { ...base, data };
});

const statusOptions = computed<SoaListOption[]>(() => {
  return (page.props as { soa_status_options?: SoaListOption[] }).soa_status_options ?? [];
});

const accountTypeOptions = computed<SoaListOption[]>(() => {
  return (page.props as { soa_account_type_options?: SoaListOption[] }).soa_account_type_options ?? [];
});

const columnHelper = createColumnHelper();
const pagination = ref({
  current_page: soas.value.current_page,
  per_page: Number(soas.value.per_page),
  total: soas.value.total
});

const filters = ref<SoaListFilters>(soaListFiltersFromUrlQuery(page.url));
const hasInitialized = ref(false);
const isFirstLoad = ref(true);
const filtersBootstrapped = ref(false);

const searchedAccountName = ref('');
const searchedBranchName = ref('');
const searchedBillingRef = ref('');
const accounts = ref<SearchableComboboxItem[]>([]);
const branches = ref<SearchableComboboxItem[]>([]);
const billing_refs = ref<SearchableComboboxItem[]>([]);
const accountPage = ref(1);
const accountLastPage = ref(1);
const branchPage = ref(1);
const branchLastPage = ref(1);
const billingRefPage = ref(1);
const billingRefLastPage = ref(1);
const accountsLoadingMore = ref(false);
const branchesLoadingMore = ref(false);
const billingRefsLoadingMore = ref(false);
const hasMoreAccounts = computed(() => accountPage.value < accountLastPage.value);
const hasMoreBranches = computed(() => branchPage.value < branchLastPage.value);
const hasMoreBillingRefs = computed(() => billingRefPage.value < billingRefLastPage.value);

const selectedAccountFilter = computed(() =>
  accounts.value?.find((a: SearchableComboboxItem) => String(a.value) === String(filters.value.account_code)),
);

const listFetchPath = computed(() => {
  const raw = (page.url ?? '').split('?')[0] || '';
  return raw.startsWith('/') ? raw : `/${slug.value}`;
});

const partialReloadKey = computed(() => slug.value || 'soas');

const accountTypeModel = computed({
  get: () => (filters.value.account_type === '' ? undefined : filters.value.account_type),
  set: (v: string | undefined) => {
    filters.value.account_type = v ?? '';
  },
});

const statusFilterModel = computed({
  get: () => (filters.value.status === '' ? undefined : filters.value.status),
  set: (v: string | undefined) => {
    filters.value.status = v ?? '';
  },
});

const baseColumns: any[] = [
  columnHelper.accessor('soa_number', {
    header: 'Billing Invoice',
  }),
  columnHelper.accessor('account_name', {
    header: 'Account',
  }),
  columnHelper.accessor('branch_name', {
    header: 'Branch',
  }),
  columnHelper.accessor('created_at', {
    header: 'Bill Date',
  }),
  columnHelper.accessor('due_in', {
    header: 'Due In',
  }),
  columnHelper.accessor('status', {
    header: 'Status',
    cell: ({ row, getValue }) => {
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
]

const handlerMap: Record<string, Function> = {
  view: viewSoa,
  show: viewSoa,
  edit: editSoa,
  update: editSoa,
  delete: deleteSoa,
  destroy: deleteSoa,
  manage_file: manageFile,
  file_list: fileList,
  untag: untagSoa,
}

const columns = computed(() => {
  const rawModules = (page.props as { sub_modules?: { slug: string }[] }).sub_modules ?? []
  const subModules = rawModules
    .filter((m: { slug: string }) => hasPermission(m.slug) && m.slug.split('.')[1] != 'create')
    .map((m: { slug: string }) => ({
      ...m,
      handler: handlerMap[m.slug.split('.')[1]],
    }))

  return subModules.length
    ? [...baseColumns, createActionColumn(subModules)]
    : baseColumns
})

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Soa list',
    href: slug.value,
  },
];

const fetchSoas = () => {
  const params: Record<string, string | number> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
    ...soaListFiltersToParams(filters.value),
  };

  router.get(
    listFetchPath.value,
    params,
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: [partialReloadKey.value]
    }
  );
};

const markInteracted = () => {
  hasInitialized.value = true;
};

const suppressFilterWatch = ref(false);

const resetDependentLists = () => {
  accounts.value = [];
  branches.value = [];
  billing_refs.value = [];
  accountPage.value = 1;
  accountLastPage.value = 1;
  branchPage.value = 1;
  branchLastPage.value = 1;
  billingRefPage.value = 1;
  billingRefLastPage.value = 1;
};

const clearFilters = () => {
  if (filterWatchTimeout.value) {
    clearTimeout(filterWatchTimeout.value);
    filterWatchTimeout.value = null;
  }
  suppressFilterWatch.value = true;
  filters.value = emptySoaListFilters();
  searchedAccountName.value = '';
  searchedBranchName.value = '';
  searchedBillingRef.value = '';
  resetDependentLists();
  markInteracted();
  pagination.value.current_page = 1;
  fetchSoas();
  nextTick(() => {
    suppressFilterWatch.value = false;
  });
};

const filtersActive = computed(() => soaListFiltersActive(filters.value));

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
      fetchSoas();
    }, filterDebounceMs);
  },
  { deep: true }
);

watch(
  () => filters.value.account_type,
  () => {
    if (!filtersBootstrapped.value || suppressFilterWatch.value) return;
    filters.value.account_code = '';
    filters.value.branch_code = '';
    filters.value.billing_ref = '';
    searchedAccountName.value = '';
    searchedBranchName.value = '';
    searchedBillingRef.value = '';
    resetDependentLists();
  },
);

watch(
  () => filters.value.account_code,
  () => {
    if (!filtersBootstrapped.value || suppressFilterWatch.value) return;
    filters.value.branch_code = '';
    filters.value.billing_ref = '';
    searchedBranchName.value = '';
    searchedBillingRef.value = '';
    branches.value = [];
    billing_refs.value = [];
    branchPage.value = 1;
    branchLastPage.value = 1;
    billingRefPage.value = 1;
    billingRefLastPage.value = 1;
  },
);

const searchAccountsByParams = async (name = '', pageNum = 1, append = false) => {
  if (!filters.value.account_type) {
    accounts.value = [];
    return;
  }
  if (append) {
    accountsLoadingMore.value = true;
  }
  const result = await getAccountsByParams({
    type: filters.value.account_type,
    name,
    page: pageNum,
  });
  if (append) {
    accounts.value = [...(accounts.value ?? []), ...(result?.data ?? [])];
  } else {
    accounts.value = result?.data ?? [];
  }
  accountPage.value = result?.current_page ?? 1;
  accountLastPage.value = result?.last_page ?? 1;
  accountsLoadingMore.value = false;
};

const searchBranchesByParams = async (name = '', pageNum = 1, append = false) => {
  if (!selectedAccountFilter.value?.value) {
    branches.value = [];
    return;
  }
  if (append) {
    branchesLoadingMore.value = true;
  }
  const result = await getBranchesByParams({
    account_code: selectedAccountFilter.value.value,
    name,
    page: pageNum,
  });
  if (append) {
    branches.value = [...(branches.value ?? []), ...(result?.data ?? [])];
  } else {
    branches.value = result?.data ?? [];
  }
  branchPage.value = result?.current_page ?? 1;
  branchLastPage.value = result?.last_page ?? 1;
  branchesLoadingMore.value = false;
};

const searchBillingRefsByParams = async (name = '', pageNum = 1, append = false) => {
  if (!selectedAccountFilter.value?.value) {
    billing_refs.value = [];
    return;
  }
  if (append) {
    billingRefsLoadingMore.value = true;
  }
  const result = await getBillingRefsByParams({
    account_code: selectedAccountFilter.value.value,
    name,
    page: pageNum,
  });
  if (append) {
    billing_refs.value = [...(billing_refs.value ?? []), ...(result?.data ?? [])];
  } else {
    billing_refs.value = result?.data ?? [];
  }
  billingRefPage.value = result?.current_page ?? 1;
  billingRefLastPage.value = result?.last_page ?? 1;
  billingRefsLoadingMore.value = false;
};

const debouncedGetAccounts: (...args: unknown[]) => void = debounce((evOrName?: unknown) => {
  const name = typeof evOrName === 'string' ? evOrName : ((evOrName as { target?: { value?: string } })?.target?.value ?? '');
  void searchAccountsByParams(name, 1, false);
});

const debouncedGetBranches: (...args: unknown[]) => void = debounce((evOrName?: unknown) => {
  const name = typeof evOrName === 'string' ? evOrName : ((evOrName as { target?: { value?: string } })?.target?.value ?? '');
  void searchBranchesByParams(name, 1, false);
});

const debouncedGetBillingRefs: (...args: unknown[]) => void = debounce((evOrName?: unknown) => {
  const name = typeof evOrName === 'string' ? evOrName : ((evOrName as { target?: { value?: string } })?.target?.value ?? '');
  void searchBillingRefsByParams(name, 1, false);
});

function loadMoreData(kind: 'accounts' | 'branches' | 'billingRefs') {
  switch (kind) {
    case 'accounts':
      if (!hasMoreAccounts.value || accountsLoadingMore.value) return;
      void searchAccountsByParams(searchedAccountName.value, accountPage.value + 1, true);
      break;
    case 'branches':
      if (!hasMoreBranches.value || branchesLoadingMore.value) return;
      void searchBranchesByParams(searchedBranchName.value, branchPage.value + 1, true);
      break;
    case 'billingRefs':
      if (!hasMoreBillingRefs.value || billingRefsLoadingMore.value) return;
      void searchBillingRefsByParams(searchedBillingRef.value, billingRefPage.value + 1, true);
      break;
  }
}

watch([() => filters.value.account_type, searchedAccountName], async () => {
  accounts.value = [];
  if (filters.value.account_type) {
    if (searchedAccountName.value.length > 0) {
      debouncedGetAccounts(searchedAccountName.value);
    } else {
      await searchAccountsByParams();
    }
  }
}, { immediate: true });

watch([selectedAccountFilter, searchedBranchName, searchedBillingRef], async () => {
  if (selectedAccountFilter.value?.value != null) {
    if (searchedBranchName.value.length > 0) {
      debouncedGetBranches(searchedBranchName.value);
    } else {
      await searchBranchesByParams();
    }
    if (searchedBillingRef.value.length > 0) {
      debouncedGetBillingRefs(searchedBillingRef.value);
    } else {
      await searchBillingRefsByParams();
    }
  } else {
    branches.value = [];
    billing_refs.value = [];
  }
}, { immediate: true });

onMounted(async () => {
  if (filters.value.account_type) {
    await searchAccountsByParams(searchedAccountName.value, 1, false);
    if (filters.value.account_code) {
      await searchBranchesByParams();
      await searchBillingRefsByParams();
    }
  }
  await nextTick();
  filtersBootstrapped.value = true;
});

const isUpdatingFromServer = ref(false)
watch(
  soasFromProps,
  (next) => {
    if (!next) return;
    clearSoaListRowPatches();
    isUpdatingFromServer.value = true;
    pagination.value.current_page = next.current_page;
    pagination.value.per_page = Number(next.per_page);
    pagination.value.total = next.total;

    if (isFirstLoad.value && next.total > 0) {
      isFirstLoad.value = false;
    }

    setTimeout(() => {
      isUpdatingFromServer.value = false;
    }, 300);
  },
);

const fetchTimeout = ref<number | null>(null)
watch(
    () => [pagination.value.current_page, pagination.value.per_page],
    ([currentPage, perPage]) => {
        if (!hasInitialized.value) return
        if (isUpdatingFromServer.value) return

        if (fetchTimeout.value) {
            clearTimeout(fetchTimeout.value)
        }

        fetchTimeout.value = window.setTimeout(() => {
            pagination.value.current_page = Number(currentPage) || 1
            pagination.value.per_page = Number(perPage) || 10

            fetchSoas()
        }, 50)
    },
    { immediate: false }
)
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Soa list" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <div class="flex flex-col gap-4 mb-4">
              <div class="flex flex-row lg:flex-row justify-between items-stretch lg:items-start gap-4">
                <div class="flex flex-1 flex-row gap-3 min-w-0">
                  <Button class="cursor-pointer" v-if="canCreate" :onClick="newSoa">Create</Button>
                </div>
              </div>

              <div v-if="page.props.auth.user?.user_detail?.employee_no || page.props.auth.is_superadmin" class="grid gap-2 md:col-span-1">
                <Accordion type="single" collapsible>
                  <AccordionItem value="filters">
                    <AccordionTrigger class="cursor-pointer">Filters</AccordionTrigger>
                    <AccordionContent>
                      <div class="flex flex-row lg:flex-row justify-between items-stretch lg:items-start gap-4">
                          <div class="flex flex-1 flex-col gap-3 min-w-0">
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                  <div class="grid gap-2 md:col-span-1">
                                      <Label for="soa-filter-account-type">Account Type</Label>
                                      <Select v-model="accountTypeModel">
                                          <SelectTrigger id="soa-filter-account-type" class="w-full">
                                              <SelectValue placeholder="All account types" />
                                          </SelectTrigger>
                                          <SelectContent class="w-full">
                                              <SelectGroup>
                                                  <SelectLabel>Account Type</SelectLabel>
                                                  <SelectItem
                                                      v-for="opt in accountTypeOptions"
                                                      :key="String(opt.value)"
                                                      :value="String(opt.value)">
                                                      {{ opt.name }}
                                                  </SelectItem>
                                              </SelectGroup>
                                          </SelectContent>
                                      </Select>
                                  </div>

                                  <div class="md:col-span-1">
                                      <SearchableCombobox
                                          id="soa-filter-account"
                                          label="Account"
                                          :model-value="filters.account_code || null"
                                          @update:model-value="(v) => { filters.account_code = v != null ? String(v) : '' }"
                                          v-model:search="searchedAccountName"
                                          :items="accounts"
                                          placeholder="Select account…"
                                          search-placeholder="Search account…"
                                          empty-text="No account found."
                                          :disabled="!filters.account_type"
                                          :has-more="hasMoreAccounts"
                                          :loading-more="accountsLoadingMore"
                                          @load-more="loadMoreData('accounts')"
                                      />
                                  </div>

                                  <div class="md:col-span-1">
                                      <SearchableCombobox
                                          id="soa-filter-branch"
                                          label="Branch"
                                          :model-value="filters.branch_code || null"
                                          @update:model-value="(v) => { filters.branch_code = v != null ? String(v) : '' }"
                                          v-model:search="searchedBranchName"
                                          :items="branches"
                                          placeholder="Select branch…"
                                          search-placeholder="Search branch…"
                                          empty-text="No branch found."
                                          :disabled="!selectedAccountFilter"
                                          :has-more="hasMoreBranches"
                                          :loading-more="branchesLoadingMore"
                                          @load-more="loadMoreData('branches')"
                                      />
                                  </div>

                                  <div class="md:col-span-1">
                                      <SearchableCombobox
                                          id="soa-filter-billing-ref"
                                          label="Billing Ref"
                                          :model-value="filters.billing_ref || null"
                                          @update:model-value="(v) => { filters.billing_ref = v != null ? String(v) : '' }"
                                          v-model:search="searchedBillingRef"
                                          :items="billing_refs"
                                          placeholder="Select billing ref…"
                                          search-placeholder="Search billing ref…"
                                          empty-text="No billing ref found."
                                          :disabled="!selectedAccountFilter"
                                          :has-more="hasMoreBillingRefs"
                                          :loading-more="billingRefsLoadingMore"
                                          @load-more="loadMoreData('billingRefs')"
                                      />
                                  </div>

                                  <div class="grid gap-2 md:col-span-1">
                                      <Label for="soa-filter-soa-number">SOA Number / Billing Invoice</Label>
                                      <Input
                                          id="soa-filter-soa-number"
                                          v-model="filters.soanum"
                                          type="text"
                                          autocomplete="off"
                                          placeholder="SOA Number / Billing Invoice"
                                          class="mt-0"
                                      />
                                  </div>

                                  <div class="grid gap-2 md:col-span-1">
                                      <Label for="soa-filter-status">Status</Label>
                                      <Select v-model="statusFilterModel">
                                          <SelectTrigger id="soa-filter-status" class="w-full">
                                              <SelectValue placeholder="All statuses" />
                                          </SelectTrigger>
                                          <SelectContent class="w-full">
                                              <SelectGroup>
                                                  <SelectLabel>Status</SelectLabel>
                                                  <SelectItem
                                                      v-for="opt in statusOptions"
                                                      :key="String(opt.value)"
                                                      :value="String(opt.value)">
                                                      {{ opt.name }}
                                                  </SelectItem>
                                              </SelectGroup>
                                          </SelectContent>
                                      </Select>
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
                      </div>
                    </AccordionContent>
                  </AccordionItem>
                </Accordion>
              </div>
              <div v-else class="w-md">
                <div class="grid gap-2 md:col-span-1">
                  <Label for="soa-filter-soa-number">SOA Number / Billing Invoice</Label>
                  <Input
                    id="soa-filter-soa-number"
                    v-model="filters.soanum"
                    type="text"
                    autocomplete="off"
                    placeholder="SOA Number / Billing Invoice"
                    class="mt-0"
                  />
                </div>
              </div>
            </div>
            <Datatable
              :data="soas.data"
              :columns="columns"
              :pagination="pagination"
              :enable-row-click="true"
              :search-fields="[]"
              :enable-search="false"
              :row-click="openSoaFilesPane"
              empty-message="No soas found"
              empty-description="System soas will appear here. Use filters, pagination, or change rows per page to load data."
              export-file-name="soas_list"
              @update:pagination="(newPagination) => { markInteracted(); pagination = newPagination }">
            </Datatable>
        </div>

        <RightPane
          :open="rightPaneVisible"
          :title="rightPaneTitle"
          :loading="rightPaneLoading"
          :error="rightPaneError"
          :content-component="rightPaneContentComponent"
          :component-props="rightPaneComponentProps"
          @update:open="(v) => { if (!v && !rightPaneLoading) closeRightPane() }"
          />
    </AppLayout>
</template>
