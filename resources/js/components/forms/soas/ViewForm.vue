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
const soaViewForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!soaViewForm.value) return null
    return new FormData(soaViewForm.value)
}

defineExpose({
    soaViewForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: soaViewForm.value })
    }
})
</script>

<template>
  <form ref="soaViewForm" class="grid grid-cols-1 md:grid-cols-1 gap-3">
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
        <Label for="company_branch">Company / Branch</Label>
        <Input
            id="company_branch"
            class="mt-1 block w-full"
            name="company_branch"
            :default-value="soa?.company_branch"
            autocomplete="company_branch"
            placeholder="Company / Branch"
            readonly
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="billtype">Bill Type</Label>
        <Input
            id="billtype"
            class="mt-1 block w-full"
            name="billtype"
            :default-value="soa?.billtype"
            autocomplete="billtype"
            placeholder="Bill Type"
            readonly
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="billdate">Bill Date</Label>
        <Input
            id="billdate"
            class="mt-1 block w-full"
            name="billdate"
            :default-value="soa?.billdate"
            autocomplete="billdate"
            placeholder="Bill Date"
            readonly
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="upload_date">SOA Upload Date</Label>
        <Input
            id="upload_date"
            class="mt-1 block w-full"
            name="upload_date"
            :default-value="soa?.upload_date"
            autocomplete="upload_date"
            placeholder="SOA Upload Date"
            readonly
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="period_coverage">Covered Period</Label>
        <Input
            id="period_coverage"
            class="mt-1 block w-full"
            name="period_coverage"
            :default-value="soa?.period_coverage"
            autocomplete="period_coverage"
            placeholder="Covered Period"
            readonly
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="due_date">Due Date</Label>
        <Input
            id="due_date"
            class="mt-1 block w-full"
            name="due_date"
            :default-value="soa?.due_date"
            autocomplete="due_date"
            placeholder="Due Date"
            readonly
        />
    </div>

  </form>
</template>
