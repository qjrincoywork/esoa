<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import { Paperclip } from 'lucide-vue-next';
import Datatable from '@/components/Datatable.vue';
import { useAjax } from '@/composables/useAjax';
import { useModulePermissions } from '@/composables/useModulePermissions';

/** A billing attachment recorded in one side of an activity's snapshot. */
type ActivityAttachment = {
  label: string;
  name: string;
  url: string;
};

type SoaActivity = {
  id?: number;
  name?: string;
  event?: string;
  /** Human-readable event title from API */
  event_label?: string;
  /** Plain-language description (replaces raw JSON from API) */
  from?: string;
  to?: string;
  /** Files recorded before the change (e.g. the attachment an update replaced) */
  from_attachments?: ActivityAttachment[];
  /** Files recorded after the change */
  to_attachments?: ActivityAttachment[];
  created_at?: string;
};

type Snapshot = 'from' | 'to';

const props = defineProps<{
  soaId?: number | null;
}>();

const { get } = useAjax();
const { slug, hasPermission } = useModulePermissions();

/** Attachment links stream through the billing_attachments route, so they need its permission. */
const canViewAttachments = computed(() => hasPermission(`${slug.value}.billing_attachments`));

const loading = ref(false);
const activities = ref<SoaActivity[]>([]);
const error = ref('');
const fetchToken = ref(0);
const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
});

/**
 * A From/To cell: the plain-language description, then a link per attachment recorded
 * in that snapshot, so files as they were at that point can be opened from the log.
 */
const renderSnapshotCell = (activity: SoaActivity, snapshot: Snapshot) => {
  const description = h(
    'span',
    { class: 'line-clamp-2 break-words text-sm' },
    activity[snapshot] || '—'
  );
  const attachments = canViewAttachments.value
    ? activity[`${snapshot}_attachments` as const] ?? []
    : [];

  if (!attachments.length) return description;

  return h('div', { class: 'flex flex-col gap-1' }, [
    description,
    h(
      'ul',
      { class: 'flex flex-col gap-0.5' },
      attachments.map((file) =>
        h('li', { key: file.url }, [
          h(
            'a',
            {
              href: file.url,
              target: '_blank',
              rel: 'noopener noreferrer',
              title: `View ${file.label}`,
              class: 'inline-flex cursor-pointer items-center gap-1 text-xs font-medium underline underline-offset-2 hover:opacity-90',
            },
            [
              h(Paperclip, { class: 'size-3 shrink-0' }),
              h('span', { class: 'break-all' }, file.name),
            ]
          ),
        ])
      )
    ),
  ]);
};

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
    cell: ({ row }) => renderSnapshotCell(row.original, 'from'),
  }),
  columnHelper.accessor('to', {
    header: 'To',
    cell: ({ row }) => renderSnapshotCell(row.original, 'to'),
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
