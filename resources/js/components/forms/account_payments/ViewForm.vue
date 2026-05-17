<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  Card,
  CardContent,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

type AccountPayment = {
  id?: number
  user_id?: number
  billing_invoice?: string
  deposit_date?: string
  mode_of_payment?: number
  mode_of_payment_value?: number
  image?: string
  image_preview_token?: string | null
  excel?: string
  excel_preview_token?: string | null
  pdf?: string
  pdf_preview_token?: string | null
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

const openFilePreview = (type: string) => {
  if (accountPayment.value?.[`${type}_preview_token`]) {
    window.open(
      `/account_payments/preview_file?token=${encodeURIComponent(accountPayment.value[`${type}_preview_token`])}`,
      '_blank',
      'noopener,noreferrer'
    );
  }
};
</script>

<template>
  <Card>
    <CardContent class="grid gap-3">
      <ul class="my-6 ml-6 list-disc [&>li]:mt-2">
        <li v-if="accountPayment.billing_invoice">Billing Invoice/s: <span class="font-bold">{{ accountPayment.billing_invoice }}</span></li>
        <li>Deposit Date: <span class="font-bold">{{ accountPayment.deposit_date }}</span></li>
        <li>Mode of Payment: <span class="font-bold">{{ accountPayment.mode_of_payment }}</span></li>
        <li>Remarks: <p class="font-bold whitespace-pre-line">{{ accountPayment.remarks }}</p></li>
        <li>Created By: <span class="font-bold">{{ accountPayment.created_by }}</span></li>
        <li>Created At: <span class="font-bold">{{ accountPayment.created_at }}</span></li>
        <li v-if="accountPayment.image">
          <Button :onClick="() => openFilePreview('image')" class="cursor-pointer">View Image</Button>
        </li>
        <li v-if="accountPayment.pdf">
          <Button :onClick="() => openFilePreview('pdf')" class="cursor-pointer">View PDF</Button>
        </li>
        <li v-if="accountPayment.excel">
          <Button :onClick="() => openFilePreview('excel')" class="cursor-pointer">View Excel</Button>
        </li>
      </ul>
    </CardContent>
  </Card>
</template>
