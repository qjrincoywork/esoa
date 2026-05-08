<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { useAccountPayments } from '@/composables/account_payments';
import RightPane from '@/components/RightPane.vue';
import {
  emptyAccountPaymentListFilters,
  accountPaymentListFiltersToParams,
  accountPaymentListFiltersActive,
  accountPaymentListFiltersFromUrlQuery,
  type AccountPaymentListFilters,
  type AccountPaymentListOption,
} from '@/composables/accountPaymentListFilters';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion'

type AccountPaymentPagination = {
  current_page: number
  per_page: number
  total: number
  data: unknown[]
}

const page = usePage();
const { canCreate, slug, hasPermission } = useModulePermissions();
const {
  newAccountPayment,
  viewAccountPayment,
  editAccountPayment,
  deleteAccountPayment,
  previewFile,
  closePane,
  rightPaneVisible,
  rightPaneTitle,
  rightPaneLoading,
  rightPaneError,
  rightPaneContentComponent,
  rightPaneComponentProps,
} = useAccountPayments();

const accountPaymentsFromProps = computed(() => {
  const propsAccountPayments = (page.props as any).account_payments as AccountPaymentPagination | undefined;
  if (!propsAccountPayments) {
    return {
      current_page: 1,
      per_page: 10,
      total: 0,
      data: [],
    } as AccountPaymentPagination;
  }
  return propsAccountPayments;
});

const accountPayments = computed(() => accountPaymentsFromProps.value);
const filters = ref<AccountPaymentListFilters>(accountPaymentListFiltersFromUrlQuery(page.url));
const hasInitialized = ref(false);
const isUpdatingFromServer = ref(false);
const columnHelper = createColumnHelper();
const pagination = ref({
  current_page: accountPayments.value.current_page,
  per_page: Number(accountPayments.value.per_page),
  total: accountPayments.value.total,
});

const modeOfPaymentOptions = computed<AccountPaymentListOption[]>(() => {
  return (page.props as { mode_of_payment_options?: AccountPaymentListOption[] }).mode_of_payment_options ?? [];
});

const listFetchPath = computed(() => {
  const raw = (page.url ?? '').split('?')[0] || '';
  return raw.startsWith('/') ? raw : `/${slug.value}`;
});

const partialReloadKey = computed(() => slug.value || 'account_payments');

const modeOfPaymentFilterModel = computed({
  get: () => (filters.value.mode_of_payment === '' ? undefined : filters.value.mode_of_payment),
  set: (v: string | undefined) => {
    filters.value.mode_of_payment = v ?? '';
  },
});

const depositDateFilterModel = computed({
  get: () => filters.value.deposit_date,
  set: (v: string | undefined) => {
    filters.value.deposit_date = v ?? '';
  },
});

const baseColumns: any[] = [
  columnHelper.accessor('deposit_date', {
    header: 'Deposit Date',
    cell: (info: any) => info.getValue(),
  }),
  columnHelper.accessor('mode_of_payment', {
    header: 'Mode of Payment',
    cell: (info: any) => info.getValue(),
  }),
  columnHelper.accessor('created_by', {
    header: 'Created By',
    cell: (info: any) => info.getValue(),
  }),
  columnHelper.accessor('created_at', {
    header: 'Created At',
    cell: (info: any) => info.getValue(),
  }),
];

const handlerMap: Record<string, (row: any) => void> = {
  edit: editAccountPayment,
  update: editAccountPayment,
  delete: deleteAccountPayment,
  destroy: deleteAccountPayment,
  preview_file: previewFile,
}

const columns = computed(() => {
  const rawModules = (page.props as { sub_modules?: { slug: string }[] }).sub_modules ?? []
  const subModules = rawModules
    .filter((m: { slug: string }) => hasPermission(m.slug)
      && m.slug.split('.')[1] != 'create'
    )
    .map((m: { slug: string }) => ({
      ...m,
      handler: handlerMap[m.slug.split('.')[1]],
    }))

  return subModules.length
    ? [...baseColumns, createActionColumn(subModules as any)]
    : baseColumns
})

const fetchAccountPayments = () => {
  const params: Record<string, string | number> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
    ...accountPaymentListFiltersToParams(filters.value),
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

const filtersActive = computed(() => accountPaymentListFiltersActive(filters.value));

const clearFilters = () => {
  filters.value = emptyAccountPaymentListFilters();
  markInteracted();
  pagination.value.current_page = 1;
  fetchAccountPayments();
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
      fetchAccountPayments();
    }, 500);
  },
  { deep: true }
);

watch(
  accountPaymentsFromProps,
  (next: AccountPaymentPagination) => {
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
  () => [pagination.value.current_page, pagination.value.per_page] as const,
  ([currentPage, perPage]: readonly [number, number]) => {
    if (!hasInitialized.value || isUpdatingFromServer.value) return;
    if (paginationWatchTimeout.value) {
      clearTimeout(paginationWatchTimeout.value);
    }
    paginationWatchTimeout.value = window.setTimeout(() => {
      pagination.value.current_page = Number(currentPage) || 1;
      pagination.value.per_page = Number(perPage) || 10;
      fetchAccountPayments();
    }, 50);
  },
);

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Remittance Advices',
    href: slug.value,
  },
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Remittance Advices" />

    <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
      <div class="flex flex-col gap-4 mb-4">
        <div class="flex flex-row lg:flex-row justify-between items-stretch lg:items-start gap-4">
          <div class="flex flex-1 flex-row gap-3 min-w-0">
            <Button class="cursor-pointer" v-if="canCreate" :onClick="newAccountPayment">Upload Remittance Advice</Button>
          </div>
        </div>
        <div class="grid gap-2 md:col-span-1 w-1/2">
          <Accordion type="single" collapsible>
            <AccordionItem value="filters">
              <AccordionTrigger class="cursor-pointer">Filters</AccordionTrigger>
              <AccordionContent>
                <div class="flex flex-row lg:flex-row justify-between items-stretch lg:items-start gap-4">
                  <div class="flex flex-1 flex-col gap-3 min-w-0">
                    <div class="grid gap-2 md:col-span-1">
                      <Label for="account-payment-filter-deposit-date">Deposit Date</Label>
                      <Input
                        id="account-payment-filter-deposit-date"
                        v-model="depositDateFilterModel"
                        type="date"
                        autocomplete="off"
                        placeholder="Deposit Date"
                        class="mt-0"
                      />
                    </div>
                    <div class="grid gap-2 md:col-span-1">
                      <Label for="account-payment-filter-created-by">Created By</Label>
                      <Input
                        id="account-payment-filter-created-by"
                        v-model="filters.created_by"
                        type="text"
                        autocomplete="off"
                        placeholder="Created By"
                        class="mt-0"
                      />
                    </div>
                    <div class="grid gap-2 md:col-span-1">
                      <Label for="account-payment-filter-mode">Mode of Payment</Label>
                      <Select v-model="modeOfPaymentFilterModel">
                        <SelectTrigger id="account-payment-filter-mode" class="w-full">
                          <SelectValue placeholder="All modes" />
                        </SelectTrigger>
                        <SelectContent class="w-full">
                          <SelectGroup>
                            <SelectLabel>Mode of Payment</SelectLabel>
                            <SelectItem
                              v-for="opt in modeOfPaymentOptions"
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
      </div>
      <Datatable
        :data="accountPayments.data"
        :enable-row-click="true"
        :enable-search="false"
        :row-click="viewAccountPayment"
        :columns="columns"
        :pagination="pagination"
        :search-fields="[]"
        empty-message="No data found"
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
