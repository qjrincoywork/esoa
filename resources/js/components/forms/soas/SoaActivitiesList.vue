<script setup lang="ts">
import { h, onMounted, ref, watch } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import Datatable from '@/components/Datatable.vue';
import { useAjax } from '@/composables/useAjax';
import { useModulePermissions } from '@/composables/useModulePermissions';

type SoaActivity = {
  id?: number;
  name?: string;
  event?: string;
  /** Human-readable event title from API */
  event_label?: string;
  /** Plain-language description (replaces raw JSON from API) */
  from?: string;
  to?: string;
  created_at?: string;
};

const props = defineProps<{
  soaId?: number | null;
}>();

const { get } = useAjax();
const { slug } = useModulePermissions();
const loading = ref(false);
const activities = ref<SoaActivity[]>([]);
const error = ref('');
const fetchToken = ref(0);
const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
});

const columnHelper = createColumnHelper<SoaActivity>();
const columns = [
  columnHelper.accessor('event_label', {
    id: 'event_display',
    header: 'Event',
    cell: ({ row, getValue }) => getValue() || row.original.event || '-',
  }),
  columnHelper.accessor('name', {
    header: 'Name',
    cell: ({ getValue }) => getValue() || '-',
  }),
  columnHelper.accessor('from', {
    header: 'From',
    cell: ({ getValue }) =>
      h(
        'span',
        { class: 'line-clamp-2 break-words text-sm' },
        getValue() || '—'
      ),
  }),
  columnHelper.accessor('to', {
    header: 'To',
    cell: ({ getValue }) =>
      h(
        'span',
        { class: 'line-clamp-2 break-words text-sm' },
        getValue() || '—'
      ),
  }),
  columnHelper.accessor('created_at', {
    header: 'Date',
    cell: ({ getValue }) => getValue() || '-',
  }),
];

const fetchActivities = async () => {
  if (!props.soaId) return;
  const token = ++fetchToken.value;

  loading.value = true;
  error.value = '';

  try {
    const response = await get<{
      activities?: {
        data?: SoaActivity[];
        current_page?: number;
        per_page?: number;
        total?: number;
      };
    }>(
      `/${slug.value}/${props.soaId}/activities`,
      {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
      }
    );

    if (!response.ok) {
      throw new Error('Failed to load activities');
    }

    // Ignore stale responses from previous in-flight requests.
    if (token !== fetchToken.value) return;

    const payload = response.data?.activities;
    activities.value = [...(payload?.data ?? [])];
    pagination.value.current_page = Number(payload?.current_page ?? 1);
    pagination.value.per_page = Number(payload?.per_page ?? 10);
    pagination.value.total = Number(payload?.total ?? 0);
  } catch {
    if (token !== fetchToken.value) return;
    error.value = 'Unable to load SOA activities.';
    activities.value = [];
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
  fetchActivities();
};

watch(
  () => props.soaId,
  () => {
    pagination.value.current_page = 1;
    fetchActivities();
  }
);

onMounted(fetchActivities);
</script>

<template>
  <Datatable
    :key="`${pagination.current_page}-${pagination.per_page}-${activities.length}`"
    :data="activities"
    :columns="columns"
    :pagination="pagination"
    :enable-search="false"
    :search-fields="[]"
    :loading="loading"
    :error="error"
    empty-message="No SOA activities found"
    empty-description="Activities for this SOA will appear here."
    export-file-name="soa_activities"
    @update:pagination="handlePaginationUpdate" />
</template>
