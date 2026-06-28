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

const paginatedItems = computed(() =>
  props.items.slice(pageStart.value, pageStart.value + props.perPage).map((item, i) => ({
    ...item,
    globalIndex: pageStart.value + i,
  }))
);

function getAccountTypeName(value: string): string {
  return props.accountTypes.find(at => String(at.value) === value)?.name ?? value;
}
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
              <td class="px-3 py-2">{{ getAccountTypeName(ua.account_type) || '—' }}</td>
              <td class="px-3 py-2">{{ ua.account_name || ua.account_code }}</td>
              <td class="px-3 py-2">{{ ua.branch_name || ua.branch_code || '—' }}</td>
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
