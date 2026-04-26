<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';

type AccountPayment = {
  id?: number
  user_id?: number
  deposit_date?: string
  mode_of_payment?: number
  mode_of_payment_value?: number
  remittance_advice?: string
  remittance_advice_preview_token?: string | null
  remarks?: string
  created_by?: string
  created_at?: string
}

const props = defineProps({
  account_payment: {
    type: Object as unknown as () => AccountPayment,
    default: () => ({}),
  },
  mode_of_payment_options: {
    type: Array as unknown as () => { value: string | number; name: string }[],
    default: () => [],
  },
  isViewOnly: {
    type: Boolean,
    default: false,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const accountPayment = computed<AccountPayment>(() => props.account_payment as AccountPayment);

const form = ref({
  id: accountPayment.value?.id || '',
  deposit_date: accountPayment.value?.deposit_date || '',
  mode_of_payment: accountPayment.value?.mode_of_payment || '',
  remittance_advice: null as File | null,
  remarks: accountPayment.value?.remarks || '',
});

const selectedModeOfPayment = ref<string | number>(
  accountPayment.value.mode_of_payment != null ? String(accountPayment.value.mode_of_payment) : ''
);

const modeOfPaymentOptions = computed(() => props.mode_of_payment_options || []);
const accountPaymentForm = ref<HTMLFormElement | null>(null);

function getFormData(): FormData | null {
  if (!accountPaymentForm.value) return null;
  return new FormData(accountPaymentForm.value);
}

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: accountPaymentForm.value });
  }
});

const openFilePreview = () => {
  if (accountPayment.value?.remittance_advice_preview_token) {
    window.open(
      `/account_payments/preview_file?token=${encodeURIComponent(accountPayment.value.remittance_advice_preview_token)}`,
      '_blank',
      'noopener,noreferrer'
    );
  }
};
</script>

<template>
  <form ref="accountPaymentForm" class="grid grid-cols-1 md:grid-cols-2 gap-4" enctype="multipart/form-data">
    <div class="md:col-span-2 hidden">
      <!-- Use native hidden inputs so FormData always reflects latest reactive values -->
      <input v-if="accountPayment?.id" type="hidden" name="id" :value="accountPayment?.id" />
      <input type="hidden" name="mode_of_payment" :value="selectedModeOfPayment" />
    </div>

    <div class="grid gap-2">
      <Label for="deposit_date">Deposit Date<span class="text-red-400">*</span></Label>
      <Input
        class="mt-1 block w-full"
        id="deposit_date"
        name="deposit_date"
        type="date"
        v-model="form.deposit_date"
        :disabled="isViewOnly"
      />
    </div>

    <div class="grid gap-2">
      <Label for="mode_of_payment">Mode of Payment<span class="text-red-400">*</span></Label>
      <Select
        class="mt-1 block w-full"
        v-model="selectedModeOfPayment"
        :disabled="isViewOnly"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select mode of payment" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectItem
            v-for="option in modeOfPaymentOptions"
            :key="option.value"
            :value="String(option.value)"
          >
            {{ option.name }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2 md:col-span-2">
      <Label for="remittance_advice">Remittance Advice<span class="text-red-400">*</span></Label>
      <p
        v-if="accountPayment?.remittance_advice"
        class="mt-1 text-xs text-[var(--color-text-muted)]"
      >
        Current:
        <a
          @click="openFilePreview"
          target="_blank"
          rel="noopener noreferrer"
          class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
        >
          {{ accountPayment?.remittance_advice?.split('/').pop() }}
        </a>
      </p>
      <Input
        class="mt-1 block w-full"
        id="remittance_advice"
        name="remittance_advice"
        type="file"
        :disabled="isViewOnly"
      />
    </div>

    <div class="grid gap-2 md:col-span-2">
      <Label for="remarks">Remarks</Label>
      <Textarea
        placeholder="Type the remarks here."
        class="mt-1 block w-full"
        id="remarks"
        name="remarks"
        v-model="form.remarks"
        :disabled="isViewOnly"
      />
    </div>

    <!-- View mode details -->
    <template v-if="isViewOnly">
      <div class="grid gap-2">
        <Label>Created By</Label>
        <p class="mt-1 text-sm">{{ accountPayment?.created_by || '-' }}</p>
      </div>
      <div class="grid gap-2">
        <Label>Created At</Label>
        <p class="mt-1 text-sm">{{ accountPayment?.created_at || '-' }}</p>
      </div>
    </template>
  </form>
</template>
