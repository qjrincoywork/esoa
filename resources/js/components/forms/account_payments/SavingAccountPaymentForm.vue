<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { SearchableCombobox } from '@/components/ui/searchable-combobox';
import { debounce } from 'lodash-es';

type SoaRelation = {
  id: string | number;
  soa_number?: string;
  account_code?: string;
  branch_code?: string;
};

type AccountPayment = {
  id?: number
  user_id?: number
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
  soa_ids?: Array<number> | string
  soas?: SoaRelation[]
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
  image: null as File | null,
  remarks: accountPayment.value?.remarks || '',
});

const selectedModeOfPayment = ref<string | number>(
  accountPayment.value.mode_of_payment != null ? String(accountPayment.value.mode_of_payment) : ''
);

const modeOfPaymentOptions = computed(() => props.mode_of_payment_options || []);
const accountPaymentForm = ref<HTMLFormElement | null>(null);
const soaOptions = ref<BillingInvoice[]>([]);
const billingInvoicePage = ref(1);
const billingInvoiceLastPage = ref(1);
const billingInvoicesLoadingMore = ref(false);
const searchedBillingInvoice = ref('');
const selectedSoaIds = ref<(string | number)[]>(parseSoaIds(accountPayment.value?.soa_ids ?? accountPayment.value?.soas));
const hasMoreBillingInvoices = computed(() => billingInvoicePage.value < billingInvoiceLastPage.value);

function parseSoaIds(input: Array<number> | string | undefined | SoaRelation[]): (string | number)[] {
  if (!input) return [];
  if (Array.isArray(input)) {
    if (input.length > 0 && typeof input[0] === 'object') {
      return (input as SoaRelation[]).map((item) => item.id);
    }
    return input as Array<number>;
  }
  return input
    .split(',')
    .map((id) => id.trim())
    .filter(Boolean)
    .map((id) => {
      const num = parseInt(id, 10);
      return isNaN(num) ? id : num;
    });
}

function getFormData(): FormData | null {
  if (!accountPaymentForm.value) return null;
  return new FormData(accountPaymentForm.value);
}

const searchBillingInvoicesByParams = async (name = '', page = 1, append = false) => {
  if (append) {
    billingInvoicesLoadingMore.value = true;
  }

  try {
    const params: Record<string, any> = {
      soanum: name,
      page,
    };

    const response = await fetch(`/soas/list?${new URLSearchParams(params).toString()}`, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch SOAs: ${response.statusText}`);
    }

    const result = await response.json();
    const data = result?.data ?? [];

    const transformed = data.map((soa: any) => ({
      value: soa.id,
      name: `${soa.soa_number} - ${soa.account_code}${soa.branch_code ? ` (${soa.branch_code})` : ''}`,
    }));

    if (append) {
      soaOptions.value = [...soaOptions.value, ...transformed];
    } else {
      soaOptions.value = transformed;
    }

    billingInvoicePage.value = result?.current_page ?? page;
    billingInvoiceLastPage.value = result?.last_page ?? page;
  } catch (error) {
    console.error('Error fetching SOAs:', error);
    if (!append) {
      soaOptions.value = [];
    }
  } finally {
    billingInvoicesLoadingMore.value = false;
  }
};

const debouncedGetBillingInvoices: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchBillingInvoicesByParams(name, 1, false);
}, 2000);

onMounted(() => {
  void searchBillingInvoicesByParams('', 1, false);

  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: accountPaymentForm.value });
  }
});

watch(
  () => accountPayment.value,
  (accountPaymentValue: AccountPayment | undefined) => {
    selectedSoaIds.value = parseSoaIds(accountPaymentValue?.soa_ids ?? accountPaymentValue?.soas);
  },
  { immediate: true }
);

watch(searchedBillingInvoice, (newVal: string) => {
  debouncedGetBillingInvoices(newVal);
});

function loadMoreData(input: string) {
  if (input !== 'soas' || billingInvoicesLoadingMore.value || !hasMoreBillingInvoices.value) return;
  void searchBillingInvoicesByParams(searchedBillingInvoice.value, billingInvoicePage.value + 1, true);
}

const openFilePreview = (type: string) => {
  const tokenKey = `${type}_preview_token` as keyof AccountPayment;
  const token = accountPayment.value?.[tokenKey];
  if (typeof token === 'string' && token.length > 0) {
    window.open(
      `/account_payments/preview_file?token=${encodeURIComponent(token)}`,
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
      <template v-for="id in selectedSoaIds" :key="id">
        <input type="hidden" name="soa_ids[]" :value="id" />
      </template>
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
      <SearchableCombobox
        id="soa_ids"
        label="Billing Invoices"
        v-model="selectedSoaIds"
        v-model:search="searchedBillingInvoice"
        :items="soaOptions"
        placeholder="Select Billing Invoices..."
        search-placeholder="Search Billing Invoices..."
        empty-text="No Billing Invoices found."
        :has-more="hasMoreBillingInvoices"
        :loading-more="billingInvoicesLoadingMore"
        :multiple="true"
        :disabled="isViewOnly"
        @load-more="loadMoreData('soas')"
      />
    </div>

    <div class="grid gap-2 md:col-span-2">
      <Label for="image">Remittance Advice Image<span class="text-red-400">*</span></Label>
      <p
        v-if="accountPayment?.image"
        class="mt-1 text-xs text-[var(--color-text-muted)]"
      >
        Current:
        <a
          @click="openFilePreview('image')"
          target="_blank"
          rel="noopener noreferrer"
          class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
        >
          {{ accountPayment?.image?.split('/').pop() }}
        </a>
      </p>
      <Input
        class="mt-1 block w-full"
        id="image"
        name="image"
        accept=".jpeg,.png,.jpg"
        type="file"
        :disabled="isViewOnly"
      />
    </div>

    <div class="grid gap-2 md:col-span-2">
      <Label for="pdf">Remittance Advice PDF<span class="text-red-400">*</span></Label>
      <p
        v-if="accountPayment?.pdf"
        class="mt-1 text-xs text-[var(--color-text-muted)]"
      >
        Current:
        <a
          @click="openFilePreview('pdf')"
          target="_blank"
          rel="noopener noreferrer"
          class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
        >
          {{ accountPayment?.pdf?.split('/').pop() }}
        </a>
      </p>
      <Input
        class="mt-1 block w-full"
        id="pdf"
        name="pdf"
        accept=".pdf"
        type="file"
        :disabled="isViewOnly"
      />
    </div>

    <div class="grid gap-2 md:col-span-2">
      <Label for="excel">Remittance Advice Excel<span class="text-red-400">*</span></Label>
      <p
        v-if="accountPayment?.excel"
        class="mt-1 text-xs text-[var(--color-text-muted)]"
      >
        Current:
        <a
          @click="openFilePreview('excel')"
          target="_blank"
          rel="noopener noreferrer"
          class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
        >
          {{ accountPayment?.excel?.split('/').pop() }}
        </a>
      </p>
      <Input
        class="mt-1 block w-full"
        id="excel"
        name="excel"
        accept=".xlsx,.xls"
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
