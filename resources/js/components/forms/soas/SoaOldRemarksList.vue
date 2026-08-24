<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, Paperclip } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useAjax } from '@/composables/useAjax';
import { useModulePermissions } from '@/composables/useModulePermissions';

/** One message of the legacy eSOA conversation, as shaped by OldRemarkResource. */
type OldRemark = {
  id?: number | string;
  author?: string;
  side_label?: string;
  /** True when ValueCare posted it — those sit on the right in brand colour. */
  is_value_care?: boolean;
  message?: string;
  type_label?: string;
  attachment?: string | null;
  /** Short-lived, user-bound token exchanged for the file by the preview route. */
  attachment_preview_token?: string | null;
  created_at?: string;
};

const props = defineProps<{
  soaId?: number | null;
}>();

const { get } = useAjax();
const { slug } = useModulePermissions();
const loading = ref(false);
const remarks = ref<OldRemark[]>([]);
const error = ref('');
const fetchToken = ref(0);
const pagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
});

const lastPage = computed(() =>
  Math.max(1, Math.ceil(pagination.value.total / pagination.value.per_page))
);
const rangeStart = computed(() =>
  pagination.value.total === 0
    ? 0
    : (pagination.value.current_page - 1) * pagination.value.per_page + 1
);
const rangeEnd = computed(() =>
  Math.min(pagination.value.current_page * pagination.value.per_page, pagination.value.total)
);

/**
 * Caption under a message: the client picked the remark type when they raised it, so
 * it only carries meaning on their side — which is how the old chat captioned it too.
 */
const captionFor = (remark: OldRemark): string =>
  remark.is_value_care || !remark.type_label
    ? String(remark.created_at ?? '')
    : `${remark.type_label} - ${remark.created_at ?? ''}`;

/**
 * Where to open an attachment: this app streams the file from the legacy directory,
 * so the browser never touches the old host. No token means no readable file.
 */
const attachmentHrefFor = (remark: OldRemark): string | null =>
  remark.attachment_preview_token
    ? `/${slug.value}/preview_old_remark_file?token=${encodeURIComponent(remark.attachment_preview_token)}`
    : null;

const fetchRemarks = async () => {
  if (!props.soaId) return;
  const token = ++fetchToken.value;

  loading.value = true;
  error.value = '';

  try {
    const response = await get<{
      old_remarks?: {
        data?: OldRemark[];
        current_page?: number;
        per_page?: number;
        total?: number;
      };
    }>(
      `/${slug.value}/${props.soaId}/old_remarks`,
      {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
      }
    );

    if (!response.ok) {
      throw new Error('Failed to load old remarks');
    }

    // Ignore stale responses from previous in-flight requests.
    if (token !== fetchToken.value) return;

    const payload = response.data?.old_remarks;
    remarks.value = [...(payload?.data ?? [])];
    pagination.value.current_page = Number(payload?.current_page ?? 1);
    pagination.value.per_page = Number(payload?.per_page ?? 10);
    pagination.value.total = Number(payload?.total ?? 0);
  } catch {
    if (token !== fetchToken.value) return;
    error.value = 'Unable to load old remarks / concerns.';
    remarks.value = [];
    pagination.value.total = 0;
  } finally {
    if (token === fetchToken.value) {
      loading.value = false;
    }
  }
};

/** Newest messages come first, so "older" walks forward through the pages. */
const goToPage = (page: number) => {
  const target = Math.min(Math.max(page, 1), lastPage.value);
  if (target === pagination.value.current_page || loading.value) return;
  pagination.value.current_page = target;
  fetchRemarks();
};

watch(
  () => props.soaId,
  () => {
    pagination.value.current_page = 1;
    fetchRemarks();
  }
);

onMounted(fetchRemarks);
</script>

<template>
  <div class="border border-[var(--color-border)] p-6 shadow-sm">
    <p v-if="loading" class="py-8 text-center text-sm text-muted-foreground">
      Loading old remarks / concerns…
    </p>

    <p v-else-if="error" class="py-8 text-center text-sm text-red-500">
      {{ error }}
    </p>

    <div v-else-if="!remarks.length" class="py-8 text-center">
      <p class="text-sm text-muted-foreground">No old remarks / concerns found</p>
      <p class="mt-1 text-sm text-muted-foreground">
        Messages raised on this SOA in the previous system will appear here.
      </p>
    </div>

    <div v-else class="flex flex-col gap-4">
      <div
        v-for="remark in remarks"
        :key="remark.id"
        class="flex"
        :class="remark.is_value_care ? 'justify-end' : 'justify-start'"
      >
        <div
          class="max-w-[85%] rounded-lg px-4 py-3 shadow-sm md:max-w-[70%]"
          :class="[
            remark.is_value_care
              ? 'bg-primary text-primary-foreground'
              : 'bg-muted text-foreground',
            remark.is_value_care ? 'text-right' : 'text-left',
          ]"
        >
          <p class="text-sm font-semibold">{{ remark.author }}</p>
          <!-- Legacy remarks carry their own line breaks; keep them as written. -->
          <p class="mt-1 text-sm break-words whitespace-pre-line">{{ remark.message }}</p>
          <p v-if="remark.attachment" class="mt-2 text-sm">
            <a
              v-if="attachmentHrefFor(remark)"
              :href="attachmentHrefFor(remark) ?? undefined"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex cursor-pointer items-center gap-1 font-medium underline underline-offset-2 hover:opacity-90"
            >
              <Paperclip class="size-4 shrink-0" />
              <span class="break-all">{{ remark.attachment }}</span>
            </a>
            <span v-else class="inline-flex items-center gap-1 opacity-90">
              <Paperclip class="size-4 shrink-0" />
              <span class="break-all">{{ remark.attachment }}</span>
            </span>
          </p>
          <p class="mt-3 text-xs opacity-80">{{ captionFor(remark) }}</p>
        </div>
      </div>

      <div
        v-if="lastPage > 1"
        class="flex items-center justify-between border-t border-[var(--color-border)] pt-3 text-sm text-muted-foreground"
      >
        <span>{{ rangeStart }}–{{ rangeEnd }} of {{ pagination.total }}</span>
        <div class="flex gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="cursor-pointer"
            :disabled="pagination.current_page === 1"
            @click="goToPage(pagination.current_page - 1)"
          >
            <ChevronLeft class="size-4" /> Newer
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="cursor-pointer"
            :disabled="pagination.current_page === lastPage"
            @click="goToPage(pagination.current_page + 1)"
          >
            Older <ChevronRight class="size-4" />
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
