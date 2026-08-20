<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';

type AccountType = { value: string | number; name: string }

type SelectedUserAccount = {
  account_type: string
  account_code: string
  account_name: string
  branch_code: string
  branch_name: string
}

const props = defineProps({
  items: {
    type: Array as unknown as () => SelectedUserAccount[],
    default: () => [],
  },
  accountTypes: {
    type: Array as unknown as () => AccountType[],
    default: () => [],
  },
  perPage: {
    type: Number,
    default: 10,
  },
});

const emit = defineEmits<{
  remove: [index: number]
}>();

const currentPage = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(props.items.length / props.perPage)));

watch(totalPages, (total) => {
  if (currentPage.value > total) currentPage.value = total;
});

const pageStart = computed(() => (currentPage.value - 1) * props.perPage);

// Keyed once per option list so labelling a page is a lookup per row, not a scan.
const accountTypeNames = computed(
  () => new Map((props.accountTypes as AccountType[]).map((at: AccountType) => [String(at.value), at.name]))
);

/**
 * Rows of the current page with their display labels resolved.
 *
 * Names come from whoever supplied the row — the picker for a freshly added entry,
 * the server for one that was already saved or copied — so both read the same; the
 * code stands in whenever a name could not be resolved.
 */
const paginatedItems = computed(() =>
  (props.items as SelectedUserAccount[]).slice(pageStart.value, pageStart.value + props.perPage).map((item: SelectedUserAccount, i: number) => ({
    ...item,
    globalIndex: pageStart.value + i,
    accountTypeLabel: accountTypeNames.value.get(String(item.account_type ?? '')) ?? item.account_type ?? '',
    accountLabel: item.account_name + (item.account_code ? ' (' + item.account_code + ')' : '') || item.account_code || '',
    branchLabel: item.branch_name + (item.branch_code ? ' (' + item.branch_code + ')' : '') || item.branch_code || '',
  }))
);
</script>

<template>
  <template v-if="items.length">
    <div class="rounded-lg border overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-[var(--color-surface)] text-[var(--color-text-muted)]">
              <th class="px-3 py-2 text-left font-medium w-10">#</th>
              <th class="px-3 py-2 text-left font-medium">Account Type</th>
              <th class="px-3 py-2 text-left font-medium">Account</th>
              <th class="px-3 py-2 text-left font-medium">Branch</th>
              <th class="px-3 py-2 w-16"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ua in paginatedItems" :key="ua.globalIndex" class="border-b last:border-0">
              <td class="px-3 py-2 text-[var(--color-text-muted)]">{{ ua.globalIndex + 1 }}</td>
              <td class="px-3 py-2">{{ ua.accountTypeLabel || '—' }}</td>
              <td class="px-3 py-2" :title="ua.account_code">{{ ua.accountLabel || '—' }}</td>
              <td class="px-3 py-2" :title="ua.branch_code">{{ ua.branchLabel || '—' }}</td>
              <td class="px-3 py-2">
                <Button
                  type="button" variant="ghost" size="sm"
                  class="text-red-500 hover:text-red-700 h-7 px-2"
                  @click="emit('remove', ua.globalIndex)"
                >
                  Remove
                </Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="totalPages > 1" class="flex items-center justify-between px-3 py-2 border-t text-sm text-[var(--color-text-muted)]">
        <span>{{ pageStart + 1 }}–{{ Math.min(currentPage * perPage, items.length) }} of {{ items.length }}</span>
        <div class="flex gap-1">
          <Button type="button" variant="ghost" size="sm" :disabled="currentPage === 1" @click="currentPage--">
            ‹ Prev
          </Button>
          <Button type="button" variant="ghost" size="sm" :disabled="currentPage === totalPages" @click="currentPage++">
            Next ›
          </Button>
        </div>
      </div>
    </div>
  </template>
  <p v-else class="text-sm text-[var(--color-text-muted)]">No accounts added yet.</p>
</template>
