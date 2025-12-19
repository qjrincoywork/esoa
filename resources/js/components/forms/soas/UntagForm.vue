<script setup lang="ts">
import { ref, onMounted, computed, defineExpose, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';

type UntagType = { value: number; name: string }
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
  untag_types: {
    type: Array as unknown as () => UntagType[],
    required: true,
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const soa = computed<Soa>(() => props.soa as Soa)
const untag_types = computed<UntagType[]>(() => props.untag_types as UntagType[])

// Selected untag type value (bound to RadioGroup) — use number|null to match server values
const selectedType = ref<number | null>(untag_types.value?.[0]?.value ?? null)

// Initialize selectedType when available/when prop changes
watch(untag_types, (next) => {
  if ((selectedType.value === null || selectedType.value === undefined) && next && next.length) {
    selectedType.value = next[0].value
  }
}, { immediate: true })

// Show reason textarea only when selected type name indicates 'other' / value is 4
const showReason = computed(() => {
  const sel = selectedType.value
  if (sel === null || sel === undefined) return false
  return sel === 4
})

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
  // ensure initial selected type when component mounts
  if ((selectedType.value === null || selectedType.value === undefined) && untag_types.value.length) {
    selectedType.value = untag_types.value[0].value
  }
})
</script>

<template>
  <form ref="soaViewForm" class="grid grid-cols-1 md:grid-cols-1 gap-3">
    <div class="md:col-span-2 hidden">
      <Input
        type="hidden"
        class="mt-1 block w-full"
        name="id"
        :default-value="soa?.id"
      />
      <Input
        type="hidden"
        class="mt-1 block w-full"
        name="soanum"
        :default-value="soa?.soanum"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <RadioGroup v-model="selectedType">
        <div class="flex items-center space-x-2" v-for="type in untag_types" :key="type.value">
          <RadioGroupItem
            :id="type.name"
            :value=type.value
            name="untag_type"
          />
          <Label :for="type.name">{{ type.name }}</Label>
        </div>
      </RadioGroup>
    </div>

    <div class="grid gap-2 md:col-span-1" v-if="showReason">
      <Label for="reason">Reason</Label>
      <Textarea
        class="mt-1 block w-full"
        type="textarea"
        id="reason"
        name="reason"
        autocomplete="reason"
        placeholder="Type your reason here."
      />
    </div>

  </form>
</template>
