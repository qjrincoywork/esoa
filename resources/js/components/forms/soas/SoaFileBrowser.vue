<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from "@/components/ui/button";

const folders = ref<string[]>([])
const loading = ref(false)
type FileEntry = {
  name: string
  preview_token: string
}
type Soa = {
  id?: number
  user_id?: number
  soa_number?: string
  account_code?: string
  branch_code?: string
  billing_ref?: string
  bill_type?: number
  status?: number
  bill_date?: string
  due_date?: string
  period_date_from?: string
  period_date_to?: string
  amount?: number
  amount_paid?: number
  payment_adjustment?: number
  balance?: number
  file_pdf?: string
  file_xls?: string
}
const props = defineProps({
  soa: {
    type: Object as unknown as () => Soa,
    default: () => ({}),
  },
  files: {
    type: Array as unknown as () => FileEntry[],
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});
const files = computed<FileEntry[]>(() => props?.files ?? [])

/* Preview file via short-lived token */
const previewFile = (file: FileEntry) => {
  if (!file?.preview_token) return
  const url = `/soas/preview_file?token=${encodeURIComponent(file.preview_token)}`
  window.open(url, '_blank', 'noopener,noreferrer')
}
</script>

<template>
<div class="file-explorer">
  <!-- Loading -->
  <div v-if="loading" class="loading">
    Loading files...
  </div>

  <!-- Explorer -->
  <div v-else>
    <!-- Folders -->
    <div class="folders">
      <div
        v-for="folder in folders"
        :key="folder"
        class="folder"
      >
        📁 {{ folder }}
      </div>
    </div>

    <!-- Files -->
    <div class="files">
      <div v-if="files.length === 0">
        No files found.
      </div>
      <div
        v-for="file in files"
        :key="file.preview_token"
        class="file"
      >

      <div v-if="file">
        <span class="mr-2">
          📄 {{ file.name }}
        </span>

        <Button
          class="cursor-pointer"
          type="button"
          @click="previewFile(file)"
        >
          Preview
        </Button>
      </div>
        <!-- <a
          :href="`/soas/download_file/${file}`"
        >
          Download
        </a> -->
      </div>
    </div>
  </div>
</div>
</template>

<style scoped>
.loading {
  padding:10px;
}
</style>
