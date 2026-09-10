<script setup lang="ts">
/**
 * Maps HMS accounts and branches onto a user by dragging them into the assigned panel.
 *
 * The available panel is one list in two modes: accounts for the chosen account type,
 * and — once an account is picked — that account's branches. Dropping an account
 * assigns every branch of it; dropping a branch narrows the mapping to that branch,
 * which is the same distinction `branch_code` carries in `user_accounts`.
 *
 * Both lists are searched and paged server-side (`users.get_accounts` /
 * `users.get_branches`), so a directory of thousands is never pulled down to be
 * filtered in the browser. Anything already mapped is dropped from the choices, so the
 * panel only ever offers something that would actually change the mapping. Saving posts
 * the whole assigned set, since that endpoint treats the payload as the complete
 * intended state — it grants and revokes at once.
 */
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import DragDropTransfer from '@/components/DragDropTransfer.vue';
import { useUsers, type UserAccountMapping } from '@/composables/users';
import { debounce } from '@/composables/utilities/helper';
import { ArrowLeft, Building2, ChevronRight, Info, RotateCcw, Save, Search, X } from 'lucide-vue-next';

type Option = { value: string | number; name: string };

/**
 * A branch option carries the account it belongs to, because a branch found by a
 * directory-wide search has no other way to say which account it maps under.
 */
type BranchOption = Option & {
  account_code?: string | number;
  account_name?: string;
};

/** One row of the available panel, whichever directory it came from. */
type SourceItem = {
  key: string;
  kind: 'account' | 'branch';
  account_type: string;
  account_code: string;
  account_name: string;
  branch_code: string;
  branch_name: string;
  title: string;
  subtitle: string;
};

const props = withDefaults(
  defineProps<{
    userId: number | string;
    /** Mappings already saved for this user. */
    mappings?: UserAccountMapping[];
    accountTypes?: Option[];
    /** False when the user's type is not one that mappings apply to. */
    allowsMapping?: boolean;
    /** Cap from the user's type; null when unlimited. */
    limit?: number | null;
    typeLabel?: string | null;
  }>(),
  {
    mappings: () => [],
    accountTypes: () => [],
    allowsMapping: true,
    limit: null,
    typeLabel: null,
  },
);

const emit = defineEmits<{
  saved: [mappings: UserAccountMapping[]];
}>();

const { getAccountsByParams, getBranchesByParams, saveUserAccountMapping } = useUsers();

/**
 * Both ends must agree on what makes two mappings the same pair, so this mirrors
 * `UserAccount::mappingKey()` exactly — the account and branch codes and nothing else.
 * Account type is deliberately not part of it: it does not affect what a mapping
 * grants, so changing the type filter must not offer an already-mapped pair again.
 */
const mappingKey = (accountCode: unknown, branchCode: unknown): string =>
  [accountCode, branchCode].map((part) => String(part ?? '').trim()).join('|');

/** Account options already read "Name (CODE)"; keep the bare name so every row labels alike. */
const bareAccountName = (label: string, code: string): string => {
  const suffix = ` (${code})`;

  return label.endsWith(suffix) ? label.slice(0, -suffix.length) : label;
};

const toMapping = (row: Partial<UserAccountMapping>): UserAccountMapping => {
  const accountType = String(row.account_type ?? '');
  const accountCode = String(row.account_code ?? '');
  const branchCode = String(row.branch_code ?? '');

  return {
    id: row.id ?? null,
    key: mappingKey(accountCode, branchCode),
    account_type: accountType,
    account_type_label: row.account_type_label ?? null,
    account_code: accountCode,
    account_name: String(row.account_name ?? '') || accountCode,
    branch_code: branchCode,
    branch_name: String(row.branch_name ?? '') || branchCode,
  };
};

// ─── Assigned set ─────────────────────────────────────────────────────────
const assigned = ref<UserAccountMapping[]>([]);
/** What the server last confirmed, so "unsaved changes" is a real comparison. */
const savedKeys = ref<string[]>([]);

const syncFromProps = () => {
  assigned.value = props.mappings.map(toMapping);
  savedKeys.value = assigned.value.map((mapping) => mapping.key);
};

watch(() => props.mappings, syncFromProps, { immediate: true, deep: true });

const isDirty = computed(() => {
  const current = assigned.value.map((mapping) => mapping.key);

  return current.length !== savedKeys.value.length
    || current.some((key, index) => key !== savedKeys.value[index]);
});

// ─── Available panel ──────────────────────────────────────────────────────
const DEFAULT_ACCOUNT_TYPE = 'A'; // AccountType::TPA_HMO — spans both directories

const accountType = ref<string>(DEFAULT_ACCOUNT_TYPE);
/** Which directory the available panel is showing. */
const mode = ref<'accounts' | 'branches'>('accounts');
/** Account whose branches are listed, kept so branch rows know what they belong to. */
const focusedAccount = ref<{ code: string; name: string } | null>(null);

const accounts = ref<Option[]>([]);
const branches = ref<BranchOption[]>([]);
const search = ref('');
const loading = ref(false);
const loadingMore = ref(false);
/** How many all-mapped pages have been skipped since the last fresh load. */
const autoAdvanced = ref(0);

/** Page position of one directory list. */
type Cursor = { page: number; lastPage: number };

const freshCursor = (): Cursor => ({ page: 1, lastPage: 1 });
const hasNextPage = (cursor: Cursor): boolean => cursor.page < cursor.lastPage;

/**
 * The two directories page independently — an account search and a branch search run
 * out at different points — so each keeps its own cursor.
 */
const accountCursor = ref<Cursor>(freshCursor());
const branchCursor = ref<Cursor>(freshCursor());

const searchTerm = computed(() => search.value.trim());
const isBranchMode = computed(() => mode.value === 'branches' && focusedAccount.value !== null);

/**
 * Whether typing searches both directories at once.
 *
 * It does by default: a search matches accounts by name and branches by name across
 * the whole directory, and the results share one list. The exception is a panel
 * focused on a single account, where the search deliberately stays inside that
 * account's branches — that context was chosen on purpose.
 */
const isUnifiedSearch = computed(() => !isBranchMode.value && searchTerm.value !== '');

const hasMore = computed(() => {
  if (isBranchMode.value) return hasNextPage(branchCursor.value);
  if (isUnifiedSearch.value) {
    return hasNextPage(accountCursor.value) || hasNextPage(branchCursor.value);
  }

  return hasNextPage(accountCursor.value);
});

const toAccountItem = (account: Option): SourceItem => {
  const accountCode = String(account.value ?? '');
  const accountName = bareAccountName(account.name, accountCode);

  return {
    key: mappingKey(accountCode, ''),
    kind: 'account',
    account_type: accountType.value,
    account_code: accountCode,
    account_name: accountName,
    branch_code: '',
    branch_name: '',
    title: accountName || accountCode,
    subtitle: `${accountCode} · all branches`,
  };
};

/**
 * A branch row knows its own account when it came from a directory-wide search, and
 * borrows the focused one when the panel was drilled into an account.
 */
const toBranchItem = (branch: BranchOption): SourceItem => {
  const branchCode = String(branch.value ?? '');
  const accountCode = String(branch.account_code ?? '') || focusedAccount.value?.code || '';
  const accountName = String(branch.account_name ?? '')
    || focusedAccount.value?.name
    || accountCode;

  return {
    key: mappingKey(accountCode, branchCode),
    kind: 'branch',
    account_type: accountType.value,
    account_code: accountCode,
    account_name: accountName,
    branch_code: branchCode,
    branch_name: branch.name,
    title: branch.name || branchCode,
    subtitle: `${accountName} · branch ${branchCode}`,
  };
};

/**
 * The available rows, normalised so the transfer list — and a drop — treats an account
 * and a branch identically.
 *
 * Accounts lead, then branches, so a mixed result stays legible. Outside a search the
 * branch list is empty, which makes this one expression for every context.
 */
const sourceItems = computed<SourceItem[]>(() =>
  isBranchMode.value
    ? branches.value.map(toBranchItem)
    : [...accounts.value.map(toAccountItem), ...branches.value.map(toBranchItem)],
);

/** The mapped pairs, as a set, so filtering a page is one lookup per row. */
const assignedKeys = computed(() => new Set(assigned.value.map((mapping) => mapping.key)));

/**
 * What the panel actually offers: the loaded rows minus anything already mapped.
 *
 * Removing them rather than showing them inert keeps every row in the list actionable,
 * and it happens here — not inside the transfer list — because the transfer list
 * identifies a dragged row by its position in the array it was given.
 */
const availableItems = computed(() =>
  sourceItems.value.filter((item) => !assignedKeys.value.has(item.key)),
);

/** True once a page was loaded but every row on it turned out to be mapped already. */
const allLoadedAreMapped = computed(
  () => sourceItems.value.length > 0 && availableItems.value.length === 0,
);

/** What the panel is listing right now, for its title and its messages. */
const subject = computed(() => {
  if (isBranchMode.value) return { one: 'branch', many: 'branches', title: 'Branches' };
  if (isUnifiedSearch.value) {
    return { one: 'account or branch', many: 'accounts or branches', title: 'Accounts & Branches' };
  }

  return { one: 'account', many: 'accounts', title: 'Accounts' };
});

const emptyText = computed(() =>
  allLoadedAreMapped.value
    ? `Every ${subject.value.one} loaded here is already mapped.`
    : `No ${subject.value.many} match this search.`,
);

const sourceHint = computed(() => {
  if (isBranchMode.value) return 'Assign one branch of this account';
  if (isUnifiedSearch.value) return 'Searching accounts and branches together';

  return 'Assign an account to cover all of its branches';
});

/**
 * Whether to stand a spinner in for the whole list.
 *
 * Only while there is nothing behind it — appending a page (by "Load more" or by
 * skipping an all-mapped one) must not blank the rows already on screen.
 */
const showSourceSpinner = computed(
  () => loading.value || (loadingMore.value && availableItems.value.length === 0),
);

const fetchAccountPage = async (nextPage: number, append: boolean) => {
  const result = await getAccountsByParams({
    type: accountType.value,
    name: searchTerm.value,
    page: nextPage,
  });

  accounts.value = append ? [...accounts.value, ...(result?.data ?? [])] : (result?.data ?? []);
  accountCursor.value = { page: result?.current_page ?? 1, lastPage: result?.last_page ?? 1 };
};

const fetchBranchPage = async (nextPage: number, append: boolean) => {
  // Scoped to one account while the panel is focused on it; directory-wide otherwise,
  // which is what lets a search reach branches of accounts not on screen.
  const scope = focusedAccount.value ? { account_code: focusedAccount.value.code } : {};

  const result = await getBranchesByParams({ ...scope, name: searchTerm.value, page: nextPage });

  branches.value = append ? [...branches.value, ...(result?.data ?? [])] : (result?.data ?? []);
  branchCursor.value = { page: result?.current_page ?? 1, lastPage: result?.last_page ?? 1 };
};

/**
 * Fill the panel for whatever context it is in — one entry point, so searching, paging
 * and switching context all go through the same path.
 *
 * A search runs both directories together rather than one after the other, so asking
 * for both costs one round trip of latency instead of two. Appending advances only the
 * directories that still have pages left.
 */
const load = async (append = false) => {
  if (append) {
    loadingMore.value = true;
  } else {
    loading.value = true;
    autoAdvanced.value = 0;
  }

  const nextPageOf = (cursor: Cursor) => (append ? cursor.page + 1 : 1);
  const wanted = (cursor: Cursor) => !append || hasNextPage(cursor);

  try {
    const pending: Promise<void>[] = [];

    if (!isBranchMode.value && wanted(accountCursor.value)) {
      pending.push(fetchAccountPage(nextPageOf(accountCursor.value), append));
    }

    if ((isBranchMode.value || isUnifiedSearch.value) && wanted(branchCursor.value)) {
      pending.push(fetchBranchPage(nextPageOf(branchCursor.value), append));
    } else if (!isBranchMode.value && !isUnifiedSearch.value && !append) {
      // Browsing accounts: no branch rows until something is searched or focused.
      branches.value = [];
      branchCursor.value = freshCursor();
    }

    await Promise.all(pending);
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
};

const debouncedSearch = debounce(() => void load(false), 400);

const loadMore = () => {
  if (!hasMore.value || loadingMore.value) return;
  void load(true);
};

/**
 * Pages skipped past because every row on them was already mapped.
 *
 * Filtering mapped rows out can empty a page completely, which would read as "nothing
 * here" while the directory still has plenty to offer — so the next page is pulled
 * automatically instead of making the user click "Load more" to get past it. The count
 * is capped, and reset whenever a fresh search starts, so a long run of mapped rows
 * cannot turn into an unbounded chain of requests.
 */
const AUTO_ADVANCE_LIMIT = 5;

watch(
  [availableItems, hasMore, loading, loadingMore],
  () => {
    const stalled = availableItems.value.length === 0
      && hasMore.value
      && !loading.value
      && !loadingMore.value;

    if (!stalled || autoAdvanced.value >= AUTO_ADVANCE_LIMIT) return;

    autoAdvanced.value += 1;
    loadMore();
  },
);

/** Narrow the panel to one account's branches. */
const focusAccount = (item: SourceItem) => {
  focusedAccount.value = { code: item.account_code, name: item.account_name };
  mode.value = 'branches';
  search.value = '';
  branches.value = [];
  branchCursor.value = freshCursor();
  void load();
};

/** Leave a focused account and go back to browsing the account directory. */
const backToAccounts = () => {
  mode.value = 'accounts';
  focusedAccount.value = null;
  search.value = '';
  void load();
};

watch(accountType, () => {
  mode.value = 'accounts';
  focusedAccount.value = null;
  search.value = '';
  void load();
});

onMounted(() => void load());

// ─── Transfers ────────────────────────────────────────────────────────────
const assign = (item: SourceItem) => {
  // Mapped pairs are already filtered out of the choices; this just makes the
  // invariant local, so no path can push a second row for the same pair.
  if (assignedKeys.value.has(item.key)) return;

  assigned.value = [
    ...assigned.value,
    toMapping({
      account_type: item.account_type,
      account_code: item.account_code,
      account_name: item.account_name,
      branch_code: item.branch_code,
      branch_name: item.branch_name,
    }),
  ];
};

const unassign = (_item: UserAccountMapping, index: number) => {
  assigned.value = assigned.value.filter((_, position) => position !== index);
};

const reorder = (next: UserAccountMapping[]) => {
  assigned.value = next;
};

const clearAll = () => {
  assigned.value = [];
};

const reset = () => syncFromProps();

// ─── Save ─────────────────────────────────────────────────────────────────
const saving = ref(false);

const save = async () => {
  saving.value = true;

  try {
    const result = await saveUserAccountMapping(props.userId, assigned.value);

    if (!result?.ok) return;

    // Re-seed from the server so labels and ids match what was actually stored.
    assigned.value = (result.user_accounts ?? []).map(toMapping);
    savedKeys.value = assigned.value.map((mapping) => mapping.key);
    emit('saved', assigned.value);
  } finally {
    saving.value = false;
  }
};

const limitReached = computed(
  () => props.limit !== null && assigned.value.length >= (props.limit ?? 0),
);

const accountTypeName = computed(
  () => props.accountTypes.find((option) => String(option.value) === accountType.value)?.name ?? '',
);
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- Why the panels are inert, when they are -->
    <div
      v-if="!allowsMapping"
      class="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-50/40 px-3 py-2 text-xs text-amber-700 dark:bg-amber-900/10 dark:text-amber-300">
      <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      <span>
        <strong>{{ typeLabel || 'This user type' }}</strong> accounts are not scoped by
        account and branch mappings, so anything assigned here would have no effect.
        Change the user's type first if they need account-scoped access.
      </span>
    </div>
    <div
      v-else-if="limit !== null"
      class="flex items-start gap-2 rounded-md border border-[var(--color-border)] px-3 py-2 text-xs text-[var(--color-text-muted)]">
      <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      <span>
        <strong>{{ typeLabel || 'This user type' }}</strong> is scoped to
        {{ limit === 1 ? 'a single account / branch pair' : `${limit} account / branch pairs` }}.
        Remove the current mapping to assign a different one.
      </span>
    </div>

    <DragDropTransfer
      :source="availableItems"
      :target="assigned"
      item-key="key"
      :source-title="subject.title"
      target-title="Mapped Accounts & Branches"
      :source-hint="sourceHint"
      :source-empty="emptyText"
      target-empty="No accounts or branches mapped yet."
      :source-loading="showSourceSpinner"
      :disabled="!allowsMapping"
      :max="limit"
      reorderable
      list-class="max-h-80"
      @add="assign"
      @remove="unassign"
      @reorder="reorder">
      <!-- Directory controls: account type, breadcrumb back to accounts, and search -->
      <template #source-toolbar>
        <div class="mt-2 flex flex-col gap-2">
          <div v-if="isBranchMode" class="flex items-center gap-1 text-xs">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="h-6 shrink-0 px-1.5 text-xs"
              @click="backToAccounts">
              <ArrowLeft class="mr-1 h-3 w-3" /> Accounts
            </Button>
            <ChevronRight class="h-3 w-3 shrink-0 text-[var(--color-text-muted)]" aria-hidden="true" />
            <span class="truncate font-medium" :title="focusedAccount?.name">
              {{ focusedAccount?.name }}
            </span>
          </div>

          <div v-else class="flex items-center gap-2">
            <Label class="shrink-0 text-xs text-[var(--color-text-muted)]" for="mapping_account_type">
              Type
            </Label>
            <Select id="mapping_account_type" v-model="accountType">
              <SelectTrigger class="h-7 flex-1 text-xs">
                <SelectValue :placeholder="accountTypeName || 'Account type'" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem
                    v-for="option in accountTypes"
                    :key="String(option.value)"
                    :value="String(option.value)"
                    class="text-xs">
                    {{ option.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

          <div class="relative">
            <Search
              class="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
              aria-hidden="true" />
            <input
              v-model="search"
              type="text"
              :placeholder="isBranchMode ? 'Search branches...' : 'Search accounts or branches...'"
              class="w-full rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface)] py-1.5 pr-7 pl-7 text-xs text-[var(--color-text)] focus:border-transparent focus:ring-2 focus:ring-opacity-50"
              :style="{ '--tw-ring-color': 'var(--primary-color)' }"
              :aria-label="isBranchMode ? 'Search branches' : 'Search accounts and branches'"
              @input="debouncedSearch" />
            <button
              v-if="search"
              type="button"
              class="absolute top-1/2 right-2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
              aria-label="Clear search"
              @click="search = ''; debouncedSearch()">
              <X class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </template>

      <!-- Mapped pairs are filtered out upstream, so every row here is assignable -->
      <template #source-item="{ item }">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="flex items-center gap-1.5 truncate font-medium" :title="item.title">
              <!-- One list, two directories: say which a row came from -->
              <span
                v-if="isUnifiedSearch"
                class="shrink-0 rounded px-1 py-px text-[10px] font-semibold uppercase"
                :class="item.kind === 'branch'
                  ? 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300'
                  : 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'">
                {{ item.kind }}
              </span>
              <span class="truncate">{{ item.title }}</span>
            </p>
            <p class="truncate text-xs text-[var(--color-text-muted)]" :title="item.subtitle">
              {{ item.subtitle }}
            </p>
          </div>
          <!-- Drill into an account's branches without assigning the account itself -->
          <Button
            v-if="item.kind === 'account'"
            type="button"
            variant="ghost"
            size="sm"
            class="h-6 shrink-0 px-1.5 text-xs text-[var(--color-text-muted)]"
            :title="`Show branches of ${item.title}`"
            @click.stop="focusAccount(item)">
            <Building2 class="h-3.5 w-3.5" />
          </Button>
        </div>
      </template>

      <template #source-footer>
        <div v-if="hasMore" class="pt-2 text-center">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 text-xs"
            :disabled="loadingMore"
            @click="loadMore">
            {{ loadingMore ? 'Loading...' : 'Load more' }}
          </Button>
        </div>
      </template>

      <template #target-toolbar>
        <div v-if="assigned.length" class="mt-2 flex items-center justify-between gap-2">
          <span v-if="limitReached" class="text-xs text-amber-600 dark:text-amber-400">
            Limit reached
          </span>
          <span v-else class="text-xs text-[var(--color-text-muted)]">
            Drag to reorder
          </span>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-6 px-1.5 text-xs text-red-500 hover:text-red-700"
            :disabled="!allowsMapping"
            @click="clearAll">
            Clear all
          </Button>
        </div>
      </template>

      <template #target-item="{ item }">
        <div class="min-w-0">
          <p class="truncate font-medium" :title="item.account_name">
            {{ item.account_name }}
            <span class="text-xs font-normal text-[var(--color-text-muted)]">
              ({{ item.account_code }})
            </span>
          </p>
          <p class="truncate text-xs text-[var(--color-text-muted)]">
            <template v-if="item.branch_code">
              {{ item.branch_name }} · branch {{ item.branch_code }}
            </template>
            <template v-else>All branches</template>
            <template v-if="item.account_type_label"> · {{ item.account_type_label }}</template>
          </p>
        </div>
      </template>
    </DragDropTransfer>

    <!-- Save bar -->
    <div class="flex items-center justify-between gap-2 border-t border-[var(--color-border)] pt-3">
      <p class="text-xs text-[var(--color-text-muted)]">
        <template v-if="isDirty">Unsaved changes</template>
        <template v-else>
          {{ assigned.length }}
          {{ assigned.length === 1 ? 'mapping' : 'mappings' }} saved
        </template>
      </p>
      <div class="flex items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="cursor-pointer"
          :disabled="!isDirty || saving"
          @click="reset">
          <RotateCcw class="mr-1 h-3.5 w-3.5" /> Reset
        </Button>
        <Button
          type="button"
          size="sm"
          class="cursor-pointer"
          :disabled="!isDirty || saving || !allowsMapping"
          @click="save">
          <Save class="mr-1 h-3.5 w-3.5" />
          {{ saving ? 'Saving...' : 'Save Mapping' }}
        </Button>
      </div>
    </div>
  </div>
</template>
