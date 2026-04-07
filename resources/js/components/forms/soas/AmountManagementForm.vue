<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useSoas } from '@/composables/soas';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { dispatchNotification } from '@/components/notification';

type SoaPane = {
  id?: number
  amount?: string | number
  amount_raw?: number
}

const props = defineProps<{
  soa: SoaPane
}>()

const emit = defineEmits<{
  adjusted: [payload: { amount: string; amount_raw: number }]
}>()

const { adjustSoaAmount } = useSoas();

const operation = ref<'add' | 'deduct'>('add');
const valueInput = ref('');
const submitting = ref(false);

const currentLabel = computed(() => {
  if (props.soa.amount != null && String(props.soa.amount).trim() !== '') {
    return String(props.soa.amount);
  }
  if (props.soa.amount_raw != null) {
    return Number(props.soa.amount_raw).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  return '—';
});

const canSubmit = computed(() => !!props.soa.id && !submitting.value);

function setOperation(v: unknown) {
  operation.value = v === 'deduct' ? 'deduct' : 'add';
}

async function submit() {
  if (!props.soa.id) return;
  const n = parseFloat(String(valueInput.value).replace(/,/g, ''));
  if (Number.isNaN(n) || n <= 0) {
    dispatchNotification({ title: 'Validation', content: 'Enter a positive amount.', type: 'error' });
    return;
  }
  submitting.value = true;
  showLoader();
  try {
    const response = await adjustSoaAmount({
      soa_id: props.soa.id,
      operation: operation.value,
      amount: n,
    });
    if (!response.ok) {
      const msg = (response.data as { message?: string })?.message ?? 'Update failed.';
      dispatchNotification({ title: 'Error', content: msg, type: 'error' });
      return;
    }
    const data = response.data as { amount?: string; amount_raw?: number; message?: string };
    if (data.amount != null && data.amount_raw != null) {
      emit('adjusted', { amount: data.amount, amount_raw: data.amount_raw });
    }
    valueInput.value = '';
    dispatchNotification({ title: 'Success', content: data.message ?? 'Amount updated.', type: 'success' });
  } catch {
    dispatchNotification({ title: 'Error', content: 'Network error.', type: 'error' });
  } finally {
    submitting.value = false;
    hideLoader();
  }
}
</script>

<template>
  <div class="grid max-w-md gap-4">
    <div class="rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3 text-sm">
      <span class="text-[var(--color-text-muted)]">Current amount</span>
      <p class="text-lg font-semibold text-[var(--color-text)] tabular-nums">
        {{ currentLabel }}
      </p>
    </div>

    <div class="grid gap-2">
      <Label for="amount-op">Add or deduct</Label>
      <Select
        :model-value="operation"
        @update:model-value="setOperation">
        <SelectTrigger id="amount-op" class="w-full">
          <SelectValue placeholder="Choose operation" />
        </SelectTrigger>
        <SelectContent>
          <SelectGroup>
            <SelectLabel>Operation</SelectLabel>
            <SelectItem value="add">Add to amount</SelectItem>
            <SelectItem value="deduct">Deduct from amount</SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2">
      <Label for="amount-value">Amount</Label>
      <Input
        id="amount-value"
        v-model="valueInput"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        placeholder="e.g. 1000.50"
      />
    </div>

    <Button
      type="button"
      :disabled="!canSubmit"
      class="w-full sm:w-auto"
      @click="submit">
      Apply
    </Button>
  </div>
</template>
