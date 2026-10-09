<script setup lang="ts">
/**
 * "Assign users" on the unmapped listing: pick who should be given the selected
 * accounts and branches.
 *
 * Only users whose type is scoped by mappings are offered, searched and paged
 * server-side (`unmapped_accounts.assignable_users`). Each comes with whether they can
 * take these exact rows, decided by `App\Enums\MappingEligibility`: users who already
 * hold every row, or whom the rows would take past their type's cap, are shown but
 * cannot be picked — and the save is validated by the same rule. Picks survive a new
 * search, so a selection can be built up across several.
 *
 * The modal owns the save (`openAssignUsers`); this form only answers which users were
 * picked, through `onReady`.
 */
import { computed, onMounted, ref } from 'vue';
import FormField from '@/components/FormField.vue';
import MappingBadge from '@/components/forms/users/MappingBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
  DIRECTORY_SCOPE,
  useUnmappedAccounts,
  type AssignableUser,
  type DirectoryRow,
  type MappingTarget,
} from '@/composables/unmappedAccounts';
import { debounce } from '@/composables/utilities/helper';
import { Search, X } from 'lucide-vue-next';

const props = defineProps<{
  /** The listing rows being assigned, for the summary. */
  rows: DirectoryRow[];
  /** The same rows as the pairs the server checks eligibility against. */
  targets: MappingTarget[];
  onReady?: (api: { selectedUserIds: () => number[] }) => void;
}>();

const { getAssignableUsers } = useUnmappedAccounts();

/** One screenful of the picker. */
const PER_PAGE = 20;
/** Rows named in the summary before the rest are counted instead. */
const SUMMARY_ROWS = 6;

// ─── What is being assigned ───────────────────────────────────────────────
const isBranch = (row: DirectoryRow) => row.kind === DIRECTORY_SCOPE.BRANCH;

const rowTitle = (row: DirectoryRow) =>
  (isBranch(row) ? row.branch_name : row.account_name) || (isBranch(row) ? row.branch_code : row.account_code) || '';

const summaryRows = computed(() => props.rows.slice(0, SUMMARY_ROWS));
const hiddenRowCount = computed(() => Math.max(0, props.rows.length - SUMMARY_ROWS));

// ─── Users, a page at a time ──────────────────────────────────────────────
const search = ref('');
const users = ref<AssignableUser[]>([]);
const cursor = ref({ page: 1, lastPage: 1 });
const total = ref(0);
const loading = ref(false);
const loadingMore = ref(false);
/** Bumped per request, so a slow response to an older search cannot overwrite a newer one. */
let request = 0;

const hasMore = computed(() => cursor.value.page < cursor.value.lastPage);

const load = async (append = false) => {
  if (append && (!hasMore.value || loadingMore.value)) return;

  const ticket = ++request;

  if (append) {
    loadingMore.value = true;
  } else {
    loading.value = true;
  }

  try {
    const result = await getAssignableUsers(props.targets, {
      search: search.value.trim(),
      page: append ? cursor.value.page + 1 : 1,
      per_page: PER_PAGE,
    });

    if (!result || ticket !== request) return;

    users.value = append ? [...users.value, ...result.data] : result.data;
    cursor.value = { page: result.current_page, lastPage: result.last_page };
    total.value = result.total;
  } finally {
    if (ticket === request) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
};

const debouncedSearch = debounce(() => void load(), 400);

const clearSearch = () => {
  search.value = '';
  void load();
};

// ─── Selection ────────────────────────────────────────────────────────────
/** Picked users by id — kept whole, so a pick made under one search survives the next. */
const selected = ref(new Map<number, AssignableUser>());

const isSelected = (user: AssignableUser) => selected.value.has(user.id);

const setSelected = (user: AssignableUser, picked: boolean) => {
  if (picked && !user.eligibility.assignable) return;

  const next = new Map(selected.value);

  if (picked) {
    next.set(user.id, user);
  } else {
    next.delete(user.id);
  }

  selected.value = next;
};

const pickableOnPage = computed(() => users.value.filter((user) => user.eligibility.assignable));
const allOnPagePicked = computed(() => pickableOnPage.value.length > 0 && pickableOnPage.value.every(isSelected));

const togglePage = () => {
  const pick = !allOnPagePicked.value;
  const next = new Map(selected.value);

  pickableOnPage.value.forEach((user) => (pick ? next.set(user.id, user) : next.delete(user.id)));
  selected.value = next;
};

const clearSelection = () => {
  selected.value = new Map();
};

/** "5 mapped", or "1 / 1 mapped" for a capped type. */
const mappingText = (user: AssignableUser) =>
  user.mapping_limit === null
    ? `${user.mapping_count} mapped`
    : `${user.mapping_count} / ${user.mapping_limit} mapped`;

onMounted(() => {
  void load();
  props.onReady?.({ selectedUserIds: () => [...selected.value.keys()] });
});
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- What is being assigned -->
    <section class="flex flex-col gap-1.5" aria-label="Accounts and branches being assigned">
      <p class="text-xs font-medium text-muted-foreground">
        Assigning {{ rows.length === 1 ? 'this account or branch' : `these ${rows.length} accounts & branches` }}
      </p>
      <ul class="flex flex-wrap gap-1.5">
        <li
          v-for="row in summaryRows"
          :key="`${row.account_code}|${row.branch_code ?? ''}`"
          class="flex max-w-full min-w-0 items-center gap-1.5 rounded-md border bg-muted/40 px-2 py-1 text-xs">
          <MappingBadge :badge="row.kind_badge" />
          <span class="truncate font-medium" :title="rowTitle(row)">{{ rowTitle(row) }}</span>
          <span class="shrink-0 font-mono text-muted-foreground">
            {{ isBranch(row) ? row.branch_code : row.account_code }}
          </span>
        </li>
        <li v-if="hiddenRowCount" class="flex items-center px-1 text-xs text-muted-foreground">
          +{{ hiddenRowCount }} more
        </li>
      </ul>
      <p class="text-xs text-muted-foreground">
        An account grants every one of its branches. Users who already hold a row keep it; only what they lack is added.
      </p>
    </section>

    <!-- Search -->
    <div class="relative">
      <Search
        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        aria-hidden="true" />
      <Input
        v-model="search"
        type="text"
        class="pr-8 pl-8"
        placeholder="Search username, email or name..."
        aria-label="Search users"
        @input="debouncedSearch" />
      <button
        v-if="search"
        type="button"
        class="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
        aria-label="Clear search"
        @click="clearSearch">
        <X class="size-4" />
      </button>
    </div>

    <!-- The picker answers for the whole submission, so a refusal shows here -->
    <FormField :name="['user_ids', 'targets']" nested>
      <div class="flex flex-col rounded-md border">
        <div class="flex items-center justify-between gap-2 border-b px-3 py-2 text-xs">
          <label class="flex cursor-pointer items-center gap-2 font-medium select-none">
            <Checkbox
              :model-value="allOnPagePicked"
              :disabled="!pickableOnPage.length"
              aria-label="Select every eligible user on this page"
              @update:model-value="togglePage" />
            Users
            <span class="font-normal text-muted-foreground tabular-nums">({{ total.toLocaleString() }})</span>
          </label>
          <span v-if="selected.size" class="flex items-center gap-2">
            <span class="font-medium tabular-nums">{{ selected.size }} selected</span>
            <Button type="button" variant="ghost" size="sm" class="h-6 px-1.5 text-xs" @click="clearSelection">
              Clear
            </Button>
          </span>
        </div>

        <div class="max-h-96 overflow-y-auto" :aria-busy="loading">
          <p v-if="loading" class="py-8 text-center text-xs text-muted-foreground">Loading users...</p>

          <ul v-else-if="users.length" class="divide-y">
            <li v-for="user in users" :key="user.id">
              <label
                class="flex items-start gap-3 px-3 py-2 text-sm"
                :class="user.eligibility.assignable ? 'cursor-pointer hover:bg-muted/50' : 'cursor-not-allowed opacity-60'">
                <Checkbox
                  class="mt-0.5"
                  :model-value="isSelected(user)"
                  :disabled="!user.eligibility.assignable"
                  :aria-label="`Select ${user.username}`"
                  @update:model-value="(picked) => setSelected(user, picked === true)" />
                <span class="flex min-w-0 flex-1 flex-col">
                  <span class="flex min-w-0 items-center gap-2">
                    <span class="truncate font-medium">{{ user.username }}</span>
                    <span v-if="!user.is_active" class="shrink-0 text-xs text-muted-foreground">(inactive)</span>
                  </span>
                  <span class="truncate text-xs text-muted-foreground">
                    {{ [user.full_name, user.email].filter(Boolean).join(' · ') }}
                  </span>
                </span>
                <span class="flex shrink-0 flex-col items-end gap-1 text-xs">
                  <MappingBadge :badge="user.eligibility" />
                  <span class="text-muted-foreground">{{ user.type_label }} · {{ mappingText(user) }}</span>
                </span>
              </label>
            </li>
          </ul>

          <p v-else class="py-8 text-center text-xs text-muted-foreground">
            {{ search ? 'No users match this search.' : 'No users can be mapped to accounts and branches.' }}
          </p>

          <div v-if="hasMore && !loading" class="border-t py-2 text-center">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="h-7 text-xs"
              :disabled="loadingMore"
              @click="load(true)">
              {{ loadingMore ? 'Loading...' : 'Load more' }}
            </Button>
          </div>
        </div>
      </div>
    </FormField>
  </div>
</template>
