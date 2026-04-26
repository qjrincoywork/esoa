<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';

type AccountPayment = {
  id?: number
  deposit_date?: string
  deleted_at?: string | null
}

const props = defineProps({
  accountPayment: {
    type: Object as unknown as () => AccountPayment,
    required: true,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const accountPayment = computed<AccountPayment>(() => props.accountPayment as AccountPayment)
const accountPaymentDeleteForm = ref<HTMLFormElement | null>(null)
const message = accountPayment.value?.deleted_at ? 'Restore' : 'Delete'

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
  if (!accountPaymentDeleteForm.value) return null
  return new FormData(accountPaymentDeleteForm.value)
}

defineExpose({
  accountPaymentDeleteForm,
  getFormData,
})

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: accountPaymentDeleteForm.value })
  }
})
</script>

<template>
  <form ref="accountPaymentDeleteForm">
    <div class="hidden">
      <input type="hidden" name="id" :value="accountPayment.id" />
    </div>
    <p>Are you sure you want to {{ message.toLowerCase() }} this Remittance Advice?</p>
  </form>
</template>
