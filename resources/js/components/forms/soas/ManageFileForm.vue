<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Soa = {
  id?: number
  soanum?: string
  macode?: string
  refid?: string
  upcode?: string
  billcode?: string
  billtype?: string
  billdate?: string
  upload_date?: string
  due_date?: string
  period_coverage?: string
  paid_date?: string
  amount_due?: number
  company_branch?: string
  file_pdf?: string
  file_xls?: string
  status?: string
}


const props = defineProps({
  soa: {
    type: Object as unknown as () => Soa,
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const soa = computed<Soa>(() => props.soa as Soa)

// Expose a form ref so parent components can access without document.getElementById
const manageFileForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!manageFileForm.value) return null
    return new FormData(manageFileForm.value)
}

defineExpose({
    manageFileForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: manageFileForm.value })
    }
})
</script>

<template>
  <form ref="manageFileForm" class="grid grid-cols-1 md:grid-cols-1 gap-3">
    <div class="md:col-span-2 hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="soa?.id"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="file_pdf">PDF</Label>
        <Input
            type="file"
            id="file_pdf"
            class="mt-1 block w-full"
            name="file_pdf"
            :default-value="soa?.file_pdf"
            autocomplete="file_pdf"
            placeholder="PDF"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="file_xls">Excel File</Label>
        <Input
            type="file"
            id="file_xls"
            class="mt-1 block w-full"
            name="file_xls"
            :default-value="soa?.file_xls"
            autocomplete="file_xls"
            placeholder="Excel File"
        />
    </div>

  </form>
</template>
