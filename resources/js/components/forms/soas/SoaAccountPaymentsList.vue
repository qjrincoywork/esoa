<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import Datatable from '@/components/Datatable.vue';
import { useAjax } from '@/composables/useAjax';
import { useModulePermissions } from '@/composables/useModulePermissions';

type SoaAccountPayment = {
  id?: number;
  billing_invoice?: string | null;
  deposit_date?: string;
  mode_of_payment?: string;
  mode_of_payment_value?: number;
  image?: string;
  pdf?: string;
  excel?: string;
  remarks?: string;
  created_by?: string;
  created_at?: string;
  image_preview_token?: string | null;
  pdf_preview_token?: string | null;
  excel_preview_token?: string | null;
  deleted_at?: string | null;
};

const props = defineProps<{
  soaId?: number | null;
}>();

const { get } = useAjax();
const { slug } = useModulePermissions();
const loading = ref(false);
const payments = ref<SoaAccountPayment[]>([]);
const error = ref('');
const fetchToken = ref(0);
const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
});

const columnHelper = createColumnHelper<SoaAccountPayment>();
const columns = [
  columnHelper.accessor('deposit_date', {
    header: 'Deposit Date',
    cell: ({ getValue }) => getValue() || '-',
  }),
  columnHelper.accessor('mode_of_payment', {
    header: 'Mode of Payment',
    cell: ({ getValue }) => getValue() || '-',
  }),
  columnHelper.accessor('remarks', {
    header: 'Remarks',
    cell: ({ getValue }) => getValue() || '-',
  }),
  columnHelper.accessor('created_by', {
    header: 'Submitted By',
    cell: ({ getValue }) => getValue() || '-',
  }),
  columnHelper.accessor('created_at', {
    header: 'Date',
    cell: ({ getValue }) => getValue() || '-',
  }),
];

const fetchPayments = async () => {
  if (!props.soaId) return;
  const token = ++fetchToken.value;

  loading.value = true;
  error.value = '';

  try {
    const response = await get<{
      account_payments?: {
        data?: SoaAccountPayment[];
        current_page?: number;
        per_page?: number;
        total?: number;
      };
    }>(
      `/${slug.value}/${props.soaId}/account_payments`,
      {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
      }
    );

    if (!response.ok) {
      throw new Error('Failed to load account payments');
    }

    if (token !== fetchToken.value) return;

    const payload = response.data?.account_payments;
    payments.value = [...(payload?.data ?? [])];
    pagination.value.current_page = Number(payload?.current_page ?? 1);
    pagination.value.per_page = Number(payload?.per_page ?? 10);
    pagination.value.total = Number(payload?.total ?? 0);
  } catch {
    if (token !== fetchToken.value) return;
    error.value = 'Unable to load remittance advices.';
    payments.value = [];
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
  pagination.value = { ...pagination.value, ...newPagination };
  fetchPayments();
};

/** Navigate to the account payments index with the record ID — the index page fetches the record on arrival. */
const handleRowClick = (payment: SoaAccountPayment) => {
  if (!payment.id) return;
  router.visit('/account_payments', { data: { open: payment.id } });
};

watch(
  () => props.soaId,
  () => {
    pagination.value.current_page = 1;
    fetchPayments();
  }
);

onMounted(fetchPayments);
</script>

<template>
  <Datatable
    :key="`${pagination.current_page}-${pagination.per_page}-${payments.length}`"
    :data="payments"
    :columns="columns"
    :pagination="pagination"
    :enable-search="false"
    :enable-row-click="true"
    :row-click="handleRowClick"
    :search-fields="[]"
    :loading="loading"
    :error="error"
    empty-message="No remittance advices found"
    empty-description="Remittance advices linked to this SOA will appear here."
    export-file-name="soa_remittance_advices"
    @update:pagination="handlePaginationUpdate" />
</template>
