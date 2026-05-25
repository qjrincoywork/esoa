<script setup lang="ts">
import { h, onMounted, ref, watch } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import Datatable from '@/components/Datatable.vue';
import { useAjax } from '@/composables/useAjax';
import { useModulePermissions } from '@/composables/useModulePermissions';

type SoaConcern = {
  id?: number;
  type?: string;
  title?: string;
  description?: string;
  status?: string;
  status_color?: string;
  created_by?: string;
  created_at?: string;
};

const props = defineProps<{
  soaId?: number | null;
}>();

const { get } = useAjax();
const { slug } = useModulePermissions();
const loading = ref(false);
const concerns = ref<SoaConcern[]>([]);
const error = ref('');
const fetchToken = ref(0);
const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
});

const columnHelper = createColumnHelper<SoaConcern>();
const columns = [
  columnHelper.accessor('type', {
    header: 'Type',
    cell: ({ getValue }) => getValue() || '-',
  }),
  columnHelper.accessor('title', {
    header: 'Title',
    cell: ({ getValue }) => getValue() || '-',
  }),
  columnHelper.accessor('status', {
    header: 'Status',
    cell: ({ row, getValue }) =>
      h(
        'span',
        {
          class: [
            'px-2 py-1 rounded-md text-xs font-medium',
            row.original.status_color ?? '',
          ],
        },
        getValue() || '-'
      ),
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

const fetchConcerns = async () => {
  if (!props.soaId) return;
  const token = ++fetchToken.value;

  loading.value = true;
  error.value = '';

  try {
    const response = await get<{
      concerns?: {
        data?: SoaConcern[];
        current_page?: number;
        per_page?: number;
        total?: number;
      };
    }>(
      `/${slug.value}/${props.soaId}/concerns`,
      {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
      }
    );

    if (!response.ok) {
      throw new Error('Failed to load concerns');
    }

    if (token !== fetchToken.value) return;

    const payload = response.data?.concerns;
    concerns.value = [...(payload?.data ?? [])];
    pagination.value.current_page = Number(payload?.current_page ?? 1);
    pagination.value.per_page = Number(payload?.per_page ?? 10);
    pagination.value.total = Number(payload?.total ?? 0);
  } catch {
    if (token !== fetchToken.value) return;
    error.value = 'Unable to load concerns.';
    concerns.value = [];
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
  fetchConcerns();
};

watch(
  () => props.soaId,
  () => {
    pagination.value.current_page = 1;
    fetchConcerns();
  }
);

onMounted(fetchConcerns);
</script>

<template>
  <Datatable
    :key="`${pagination.current_page}-${pagination.per_page}-${concerns.length}`"
    :data="concerns"
    :columns="columns"
    :pagination="pagination"
    :enable-search="false"
    :search-fields="[]"
    :loading="loading"
    :error="error"
    empty-message="No concerns found"
    empty-description="Concerns linked to this SOA will appear here."
    export-file-name="soa_concerns"
    @update:pagination="handlePaginationUpdate" />
</template>
