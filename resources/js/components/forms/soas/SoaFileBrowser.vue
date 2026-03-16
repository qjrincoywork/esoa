<script setup lang="ts">
import { ref, computed } from 'vue';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { Button } from "@/components/ui/button";

const folders = ref([])
const loading = ref(false)
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
    type: Object as unknown as () => [],
    default: () => ({}),
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});
const soa = computed<Soa>(() => props.soa as Soa)
const files = computed<[]>(() => props?.files)

/* Extract filename from path */
const nameOnly = (path) => {
  return path.split('/').pop()
}

/* Preview file via POST payload */
const previewFile = (file) => {
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = '/soas/preview_file'
  form.target = '_blank'

  const input = document.createElement('input')
  input.type = 'hidden'
  input.name = 'file'
  input.value = file
  form.appendChild(input)

  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (token) {
    const csrf = document.createElement('input')
    csrf.type = 'hidden'
    csrf.name = '_token'
    csrf.value = token
    form.appendChild(csrf)
  }

  document.body.appendChild(form)
  form.submit()
  document.body.removeChild(form)
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
        📁 {{ nameOnly(folder) }}
      </div>
    </div>

    <!-- Files -->
    <div class="files">
      <div v-if="files.length === 0">
        No files found.
      </div>
      <div
        v-for="file in files"
        :key="file"
        class="file"
      >

      <div v-if="file">
        <span class="mr-2">
          📄 {{ nameOnly(file) }}
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
