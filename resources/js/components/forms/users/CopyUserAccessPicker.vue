<script setup lang="ts">
/**
 * "Copy access from another user": pick a user who already has account/branch access
 * and pull their rows into the host form's list.
 *
 * The picker owns finding the source user — searched and paged server-side through
 * `users.account_access_users`, which returns each user with their access already
 * labelled — while the host decides what adding a row means, because the hosts differ:
 * the edit form collects rows for a later submit, the mapping pane also enforces the
 * user type's mapping limit. So the host passes `apply`, which adds what it can and
 * says how that went, and the picker reports it.
 */
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { SearchableCombobox } from '@/components/ui/searchable-combobox';
import { useUsers, type CopiedUserAccess, type CopyAccessResult } from '@/composables/users';
import { debounce } from '@/composables/utilities/helper';

type CopyableUser = { value: string | number; name: string; accounts?: CopiedUserAccess[] };

const props = withDefaults(defineProps<{
  /** The user being edited, left out of the choices; empty while creating. */
  excludeUserId?: number | string | null;
  /** Adds the source user's rows to the host's list and reports the outcome. */
  apply: (rows: CopiedUserAccess[]) => CopyAccessResult;
  disabled?: boolean;
  /** Distinguishes the combobox when more than one picker is on the page. */
  idPrefix?: string;
}>(), {
  excludeUserId: null,
  disabled: false,
  idPrefix: 'copy',
});

const { getUsersWithAccounts } = useUsers();

const users = ref<CopyableUser[]>([]);
const selectedId = ref('');
const search = ref('');
const page = ref(1);
const lastPage = ref(1);
const loadingMore = ref(false);
const message = ref<{ text: string; tone: 'success' | 'muted' } | null>(null);

const hasMore = computed(() => page.value < lastPage.value);
const selectedUser = computed(() => users.value.find((user) => String(user.value) === selectedId.value));

const fetchUsers = async (name = '', nextPage = 1, append = false) => {
  if (append) loadingMore.value = true;

  try {
    const result = await getUsersWithAccounts({ name, page: nextPage, exclude_id: props.excludeUserId ?? '' });
    users.value = append ? [...users.value, ...(result?.data ?? [])] : (result?.data ?? []);
    page.value = result?.current_page ?? 1;
    lastPage.value = result?.last_page ?? 1;
  } finally {
    loadingMore.value = false;
  }
};

const debouncedSearch = debounce((name: string) => void fetchUsers(name), 400);

const onSearch = (name: string) => {
  search.value = name;
  debouncedSearch(name.trim());
};

const loadMore = () => {
  if (!hasMore.value || loadingMore.value) return;
  void fetchUsers(search.value.trim(), page.value + 1, true);
};

const plural = (count: number, word: string) => `${count} ${word}${count === 1 ? '' : 's'}`;

/** One sentence for whatever mix of added, duplicate and over-limit rows came back. */
const describe = ({ added, skipped, overLimit = 0 }: CopyAccessResult): string => {
  if (added === 0 && overLimit === 0) return 'All of that user’s accounts are already added.';

  const parts = [added > 0 ? `Copied ${plural(added, 'account')}` : 'Nothing copied'];
  if (skipped > 0) parts.push(`${skipped} already present`);
  if (overLimit > 0) parts.push(`${overLimit} left out — the mapping limit was reached`);

  return `${parts.join(', ')}.`;
};

const copy = () => {
  const source = selectedUser.value;
  if (!source) return;

  const result = props.apply((source.accounts ?? []).filter((row) => row.account_code));
  message.value = { text: describe(result), tone: result.added > 0 ? 'success' : 'muted' };

  selectedId.value = '';
  search.value = '';
};

onMounted(() => void fetchUsers());
</script>

<template>
  <div class="rounded-lg border border-[var(--color-border)] p-4 flex flex-col gap-3">
    <div class="flex flex-col gap-1">
      <p class="text-sm font-medium">Copy Access From Another User</p>
      <p class="text-xs text-[var(--color-text-muted)]">
        Pull an existing user's account/branch access into the list below. Duplicates are skipped.
      </p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 md:items-end">
      <SearchableCombobox
        :id="`${idPrefix}_user`"
        label="User"
        :required="false"
        v-model="selectedId"
        :search="search"
        :items="users"
        placeholder="Select a user..."
        search-placeholder="Search by username or email..."
        empty-text="No users with access found."
        :disabled="disabled"
        :has-more="hasMore"
        :loading-more="loadingMore"
        @update:search="onSearch"
        @load-more="loadMore"
      />
      <Button type="button" class="cursor-pointer" :disabled="disabled || !selectedUser" @click="copy">
        Copy Accounts
      </Button>
    </div>
    <p
      v-if="message"
      class="text-sm"
      :class="message.tone === 'success' ? 'text-emerald-600 dark:text-emerald-400' : 'text-[var(--color-text-muted)]'">
      {{ message.text }}
    </p>
  </div>
</template>
