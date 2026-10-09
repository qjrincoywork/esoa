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
 * panel only ever offers something that would actually change the mapping.
 *
 * The mapped panel is paged and searched server-side too (`users.mapped_accounts`):
 * labelling and badging a mapping costs HMS lookups, and a group account admin can
 * hold hundreds, so only the loaded pages are labelled. What the user holds in full is
 * known by key alone (`mappedKeys`, codes only), which is all the pickers need. Edits
 * are kept as changes on top of the saved set — pairs added, saved pairs removed, or
 * everything cleared — and saving posts just those changes.
 *
 * Every row on either panel carries its badges — account or branch, plus expired and
 * no members where they apply — decided and styled server-side by
 * `App\Enums\AccountMappingBadge`, so this component only renders what it is sent.
 * Expired accounts, and their branches, are left out of the choices unless asked for.
 */
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import DragDropTransfer from '@/components/DragDropTransfer.vue';
import CopyUserAccessPicker from '@/components/forms/users/CopyUserAccessPicker.vue';
import MappingBadge from '@/components/forms/users/MappingBadge.vue';
import {
  useUsers,
  type CopiedUserAccess,
  type CopyAccessResult,
  type MappingBadges,
  type UserAccountMapping,
} from '@/composables/users';
import { debounce } from '@/composables/utilities/helper';
import { ArrowLeft, Building2, ChevronRight, Info, RotateCcw, Save, Search, X } from 'lucide-vue-next';
import FormField from '@/components/FormField.vue';

type Option = { value: string | number; name: string };

/** An account option, carrying the badges the lookup was asked to attach. */
type AccountOption = Option & MappingBadges;

/**
 * A branch option carries the account it belongs to, because a branch found by a
 * directory-wide search has no other way to say which account it maps under.
 */
type BranchOption = Option & MappingBadges & {
  account_code?: string | number;
  account_name?: string;
};

/** One row of the available panel, whichever directory it came from. */
type SourceItem = Required<MappingBadges> & {
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

/**
 * A row's badges, normalised. They are decided server-side (`AccountMappingBadge`) and
 * travel with the row from wherever it came — a picker page, a saved mapping or a
 * copied one — so a dropped row is never re-derived here and reads like a saved one.
 */
const badgesOf = (row: MappingBadges): Required<MappingBadges> => ({
  kind_badge: row.kind_badge ?? null,
  status_badges: row.status_badges ?? [],
});

const props = withDefaults(
  defineProps<{
    userId: number | string;
    /** Key of every pair already saved for this user ({@link mappingKey}); the rows load a page at a time. */
    mappedKeys?: string[];
    accountTypes?: Option[];
    /** False when the user's type is not one that mappings apply to. */
    allowsMapping?: boolean;
    /** Cap from the user's type; null when unlimited. */
    limit?: number | null;
    typeLabel?: string | null;
  }>(),
  {
    mappedKeys: () => [],
    accountTypes: () => [],
    allowsMapping: true,
    limit: null,
    typeLabel: null,
  },
);

const emit = defineEmits<{
  /** The key of every pair the user holds once saved. */
  saved: [mappedKeys: string[]];
}>();

const { getAccountsByParams, getBranchesByParams, getUserMappedAccounts, saveUserAccountMapping } = useUsers();

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
    ...badgesOf(row),
  };
};

/** Page position of one paged list. */
type Cursor = { page: number; lastPage: number };

const freshCursor = (): Cursor => ({ page: 1, lastPage: 1 });
const hasNextPage = (cursor: Cursor): boolean => cursor.page < cursor.lastPage;

/** Whether a row contains a search term in any of its codes or names, ignoring case. */
const rowMatches = (row: UserAccountMapping, term: string): boolean => {
  const needle = term.toLowerCase();

  return [row.account_code, row.account_name, row.branch_code, row.branch_name]
    .some((field) => String(field ?? '').toLowerCase().includes(needle));
};

// ─── Saved set, a page at a time ──────────────────────────────────────────
/** Key of every pair the server last confirmed — the whole set, codes only. */
const savedKeys = ref<string[]>([]);

watch(() => props.mappedKeys, (keys) => {
  savedKeys.value = [...keys];
}, { immediate: true });

/** One screenful of the mapped panel; each page costs an HMS lookup, so keep it small. */
const MAPPED_PER_PAGE = 20;

/** The saved rows loaded so far, labelled and badged by the server. */
const mappedRows = ref<UserAccountMapping[]>([]);
const mappedCursor = ref<Cursor>(freshCursor());
const mappedSearch = ref('');
const mappedLoading = ref(false);
const mappedLoadingMore = ref(false);
/** Bumped per request, so a slow response to an older search cannot overwrite a newer one. */
let mappedRequest = 0;

const mappedSearchTerm = computed(() => mappedSearch.value.trim());

/**
 * Load the first page of saved rows for the current search, or append the next one.
 *
 * A pair HMS lists twice can come back on two pages; only its first row is kept, so
 * the panel never renders two rows under one key.
 */
const loadMapped = async (append = false) => {
  if (append && (!hasNextPage(mappedCursor.value) || mappedLoadingMore.value)) return;

  const ticket = ++mappedRequest;

  if (append) {
    mappedLoadingMore.value = true;
  } else {
    mappedLoading.value = true;
  }

  try {
    const result = await getUserMappedAccounts(props.userId, {
      search: mappedSearchTerm.value,
      page: append ? mappedCursor.value.page + 1 : 1,
      per_page: MAPPED_PER_PAGE,
    });

    if (!result || ticket !== mappedRequest) return;

    const loaded = append ? mappedRows.value : [];
    const seen = new Set(loaded.map((row) => row.key));
    const fresh = result.data.map(toMapping).filter((row) => !seen.has(row.key) && seen.add(row.key));

    mappedRows.value = [...loaded, ...fresh];
    mappedCursor.value = { page: result.current_page, lastPage: result.last_page };
  } finally {
    if (ticket === mappedRequest) {
      mappedLoading.value = false;
      mappedLoadingMore.value = false;
    }
  }
};

const debouncedMappedSearch = debounce(() => void loadMapped(), 400);

const clearMappedSearch = () => {
  mappedSearch.value = '';
  void loadMapped();
};

// ─── Pending changes on top of the saved set ──────────────────────────────
/** Pairs added since the last save, newest last, labelled from the row they were dropped from. */
const pendingAdds = ref<UserAccountMapping[]>([]);
/** Saved pairs removed since the last save, by key — kept whole so the save can name them. */
const pendingRemovals = ref(new Map<string, UserAccountMapping>());
/** Set by "Clear all": every saved pair goes on save, whatever is or is not loaded. */
const clearedSaved = ref(false);

const discardChanges = () => {
  pendingAdds.value = [];
  pendingRemovals.value = new Map();
  clearedSaved.value = false;
};

/**
 * Every pair the user would hold if saved now — the saved set minus what was removed
 * (or nothing, once cleared), plus what was added. This, not the loaded rows, is what
 * the pickers filter by and what the type's limit is counted against.
 */
const assignedKeys = computed(() => {
  const keys = new Set(
    clearedSaved.value ? [] : savedKeys.value.filter((key) => !pendingRemovals.value.has(key)),
  );

  pendingAdds.value.forEach((mapping) => keys.add(mapping.key));

  return keys;
});

const assignedCount = computed(() => assignedKeys.value.size);
const pendingAddKeys = computed(() => new Set(pendingAdds.value.map((mapping) => mapping.key)));

/**
 * What the mapped panel shows: unsaved additions first, so a drop is seen landing,
 * then the loaded saved rows that are still kept. Additions follow the panel's search
 * here, since the server only searched the saved set.
 */
const assigned = computed<UserAccountMapping[]>(() => [
  ...(mappedSearchTerm.value
    ? pendingAdds.value.filter((row) => rowMatches(row, mappedSearchTerm.value))
    : pendingAdds.value),
  ...(clearedSaved.value ? [] : mappedRows.value.filter((row) => !pendingRemovals.value.has(row.key))),
]);

/** More saved rows behind the loaded ones — moot once the saved set is cleared. */
const hasMoreMapped = computed(() => !clearedSaved.value && hasNextPage(mappedCursor.value));

const isDirty = computed(() =>
  pendingAdds.value.length > 0
  || pendingRemovals.value.size > 0
  || (clearedSaved.value && savedKeys.value.length > 0),
);

const targetEmptyText = computed(() => {
  if (mappedSearchTerm.value) return 'No mapped accounts or branches match this search.';
  if (clearedSaved.value && savedKeys.value.length) return 'Every saved mapping will be removed on save.';

  return 'No accounts or branches mapped yet.';
});

// ─── Available panel ──────────────────────────────────────────────────────
const DEFAULT_ACCOUNT_TYPE = 'A'; // AccountType::TPA_HMO — spans both directories

const accountType = ref<string>(DEFAULT_ACCOUNT_TYPE);
/** Which directory the available panel is showing. */
const mode = ref<'accounts' | 'branches'>('accounts');
/** Account whose branches are listed, kept so branch rows know what they belong to. */
const focusedAccount = ref<{ code: string; name: string } | null>(null);

const accounts = ref<AccountOption[]>([]);
const branches = ref<BranchOption[]>([]);
const search = ref('');
const loading = ref(false);
const loadingMore = ref(false);
/** How many all-mapped pages have been skipped since the last fresh load. */
const autoAdvanced = ref(0);

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

const toAccountItem = (account: AccountOption): SourceItem => {
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
    ...badgesOf(account),
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
    ...badgesOf(branch),
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

/**
 * What the panel actually offers: the loaded rows minus anything already mapped —
 * saved or pending, loaded into the mapped panel or not ({@link assignedKeys}).
 *
 * Removing them rather than showing them inert keeps every row in the list actionable,
 * and it happens here — not inside the transfer list — because the transfer list
 * identifies a dragged row by its position in the array it was given.
 */
const availableItems = computed(() => {
  // HMS has a account/branch pair duplicated in its directory, and a directory-wide
  // search surfaces it twice. Two rows sharing a key would collide as v-for keys and
  // be rejected as a duplicate mapping on save, so only the first is offered.
  const offered = new Set(assignedKeys.value);

  return sourceItems.value.filter((item) => {
    if (offered.has(item.key)) return false;
    offered.add(item.key);

    return true;
  });
});

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

/**
 * Asks the lookups to attach each row's badges (kind, expired, no members). Opt-in
 * server-side, so the other pickers sharing these endpoints are not charged for them.
 */
const WITH_BADGES = { with_badges: 1 } as const;

/**
 * On by default: mapping an account that has already expired grants nothing anyone can
 * use, and most of HMS has expired. Expiry is the server's reading (`AccountStanding`),
 * applied before paging, so a page is never emptied by it. Switched off, expired rows
 * come back, wearing their "expired" badge.
 */
const hideExpired = ref(true);

/** The params every directory lookup carries: the badges, and the expiry filter while it is on. */
const lookupOptions = computed(() => ({
  ...WITH_BADGES,
  ...(hideExpired.value ? { exclude_expired: 1 } : {}),
}));

const fetchAccountPage = async (nextPage: number, append: boolean) => {
  const result = await getAccountsByParams({
    ...lookupOptions.value,
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

  const result = await getBranchesByParams({
    ...lookupOptions.value,
    ...scope,
    name: searchTerm.value,
    page: nextPage,
  });

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

// Showing or hiding expired rows changes what every page holds, so the panel reloads in
// place — same account, same search — from the first page.
watch(hideExpired, () => void load());

// Both panels fill at once: the directory and the first page of what is mapped.
onMounted(() => {
  void load();
  void loadMapped();
});

// ─── Transfers ────────────────────────────────────────────────────────────
/**
 * Map one pair. A saved pair that was removed and is dropped back simply stops being
 * removed; anything else becomes a pending addition.
 */
const stage = (mapping: UserAccountMapping) => {
  // Mapped pairs are already filtered out of the choices; this just makes the
  // invariant local, so no path can stage a second row for the same pair.
  if (assignedKeys.value.has(mapping.key)) return;

  if (!clearedSaved.value && pendingRemovals.value.has(mapping.key)) {
    const removals = new Map(pendingRemovals.value);
    removals.delete(mapping.key);
    pendingRemovals.value = removals;

    return;
  }

  pendingAdds.value = [...pendingAdds.value, mapping];
};

const assign = (item: SourceItem) => stage(toMapping({
  account_type: item.account_type,
  account_code: item.account_code,
  account_name: item.account_name,
  branch_code: item.branch_code,
  branch_name: item.branch_name,
  ...badgesOf(item),
}));

/** Unmap one row: an unsaved addition is just dropped, a saved pair is marked for removal. */
const unassign = (item: UserAccountMapping) => {
  if (pendingAddKeys.value.has(item.key)) {
    pendingAdds.value = pendingAdds.value.filter((mapping) => mapping.key !== item.key);

    return;
  }

  pendingRemovals.value = new Map(pendingRemovals.value).set(item.key, item);
};

/** Unmap everything — the saved set as a whole, loaded or not, and anything staged. */
const clearAll = () => {
  pendingAdds.value = [];
  pendingRemovals.value = new Map();
  clearedSaved.value = true;
};

const reset = () => discardChanges();

/**
 * Pull another user's access into the assigned set — the "Copy Access" picker's `apply`.
 *
 * Goes through the same normalisation and key as a dragged row, so a copied pair that
 * is already mapped is skipped, and the type's limit is honoured: rows past it are
 * counted and left out rather than silently dropped. Nothing is saved; the copy lands
 * as unsaved changes to review, exactly like a drag.
 */
const copyAccess = (rows: CopiedUserAccess[]): CopyAccessResult => {
  const result: Required<CopyAccessResult> = { added: 0, skipped: 0, overLimit: 0 };

  for (const row of rows) {
    const mapping = toMapping({
      account_type: String(row.account_type ?? ''),
      account_code: String(row.account_code ?? ''),
      account_name: row.account_name ?? '',
      branch_code: String(row.branch_code ?? ''),
      branch_name: row.branch_name ?? '',
      ...badgesOf(row),
    });

    if (assignedKeys.value.has(mapping.key)) {
      result.skipped++;
    } else if (limitReached.value) {
      result.overLimit++;
    } else {
      stage(mapping);
      result.added++;
    }
  }

  return result;
};

// ─── Save ─────────────────────────────────────────────────────────────────
const saving = ref(false);

/**
 * Post the pending changes, then start over from what the server now holds: its keys
 * for the pickers, and a fresh first page — same search — for the mapped panel.
 */
const save = async () => {
  saving.value = true;

  try {
    const result = await saveUserAccountMapping(props.userId, {
      added: pendingAdds.value,
      removed: clearedSaved.value ? [] : [...pendingRemovals.value.values()],
      clear_existing: clearedSaved.value,
    });

    if (!result?.ok) return;

    savedKeys.value = result.mapped_keys ?? [];
    discardChanges();
    emit('saved', savedKeys.value);
    void loadMapped();
  } finally {
    saving.value = false;
  }
};

const limitReached = computed(
  () => props.limit !== null && assignedCount.value >= props.limit,
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

    <!-- Seed the mapping from someone who already has the right access -->
    <CopyUserAccessPicker
      v-if="allowsMapping"
      id-prefix="mapping_copy"
      :exclude-user-id="userId"
      :apply="copyAccess"
      :disabled="saving || limitReached" />

    <!--
      The transfer panel is the field: a rejected mapping is rejected as a set, or on a
      row that only exists inside it, so there is no single control to mark instead.
    -->
    <FormField :name="['added', 'removed']" nested>
      <DragDropTransfer
        :source="availableItems"
        :target="assigned"
        :target-count="assignedCount"
        item-key="key"
        :source-title="subject.title"
        target-title="Mapped Accounts & Branches"
        :source-hint="sourceHint"
        :source-empty="emptyText"
        :target-empty="targetEmptyText"
        :source-loading="showSourceSpinner"
        :target-loading="mappedLoading"
        :disabled="!allowsMapping"
        :max="limit"
        list-class="max-h-100"
        @add="assign"
        @remove="unassign">
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

            <!-- On by default: an expired account (or a branch of one) is rarely worth mapping -->
            <label class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground select-none">
              <Switch v-model="hideExpired" />
              Hide expired accounts &amp; branches
            </label>
          </div>
        </template>

        <!-- Mapped pairs are filtered out upstream, so every row here is assignable -->
        <template #source-item="{ item }">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="flex items-center gap-1.5 truncate font-medium" :title="item.title">
                <!-- Always say what a drop would map: the whole account, or one branch -->
                <MappingBadge v-if="item.kind_badge" :badge="item.kind_badge" />
                <span class="truncate">{{ item.title }}</span>
              </p>
              <p class="truncate text-xs text-[var(--color-text-muted)]" :title="item.subtitle">
                {{ item.subtitle }}
              </p>
              <div v-if="item.status_badges.length" class="mt-1 flex flex-wrap gap-1">
                <MappingBadge v-for="badge in item.status_badges" :key="badge.value" :badge="badge" />
              </div>
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

        <!-- Mapped controls: search the saved set server-side, see what is pending, clear -->
        <template #target-toolbar>
          <div class="mt-2 flex flex-col gap-2">
            <div v-if="savedKeys.length || pendingAdds.length" class="relative">
              <Search
                class="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true" />
              <Input
                v-model="mappedSearch"
                type="text"
                placeholder="Search mapped accounts or branches..."
                class="h-7 pr-7 pl-7 text-xs"
                aria-label="Search mapped accounts and branches"
                @input="debouncedMappedSearch" />
              <button
                v-if="mappedSearch"
                type="button"
                class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                aria-label="Clear mapped search"
                @click="clearMappedSearch">
                <X class="h-3.5 w-3.5" />
              </button>
            </div>

            <div v-if="assignedCount || isDirty" class="flex items-center justify-between gap-2">
              <span v-if="limitReached" class="text-xs text-amber-600 dark:text-amber-400">
                Limit reached
              </span>
              <span v-else-if="isDirty" class="text-xs text-muted-foreground">
                <template v-if="clearedSaved">All saved cleared</template>
                <template v-else-if="pendingRemovals.size">{{ pendingRemovals.size }} to remove</template>
                <template v-if="(clearedSaved || pendingRemovals.size) && pendingAdds.length"> · </template>
                <template v-if="pendingAdds.length">{{ pendingAdds.length }} to add</template>
              </span>
              <span v-else />
              <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-6 px-1.5 text-xs text-red-500 hover:text-red-700"
                :disabled="!allowsMapping || !assignedCount"
                @click="clearAll">
                Clear all
              </Button>
            </div>
          </div>
        </template>

        <template #target-item="{ item }">
          <div class="min-w-0">
            <p class="flex items-center gap-1.5 font-medium" :title="item.account_name">
              <!-- Same badges as the row it was dropped from, so the two panels read alike -->
              <MappingBadge v-if="item.kind_badge" :badge="item.kind_badge" />
              <!-- Staged but not yet saved, so a drop is told apart from what is stored -->
              <span
                v-if="pendingAddKeys.has(item.key)"
                class="shrink-0 rounded px-1 text-[10px] font-medium text-emerald-700 ring-1 ring-emerald-600/30 dark:text-emerald-400">
                Unsaved
              </span>
              <span class="truncate">
                {{ item.account_name }}
                <span class="text-xs font-normal text-[var(--color-text-muted)]">
                  ({{ item.account_code }})
                </span>
              </span>
            </p>
            <p class="truncate text-xs text-[var(--color-text-muted)]">
              <template v-if="item.branch_code">
                {{ item.branch_name }} · branch {{ item.branch_code }}
              </template>
              <template v-else>All branches</template>
              <template v-if="item.account_type_label"> · {{ item.account_type_label }}</template>
            </p>
            <div v-if="item.status_badges?.length" class="mt-1 flex flex-wrap gap-1">
              <MappingBadge v-for="badge in item.status_badges" :key="badge.value" :badge="badge" />
            </div>
          </div>
        </template>

        <template #target-footer>
          <div v-if="hasMoreMapped && !mappedLoading" class="pt-2 text-center">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="h-7 text-xs"
              :disabled="mappedLoadingMore"
              @click="loadMapped(true)">
              {{ mappedLoadingMore ? 'Loading...' : 'Load more' }}
            </Button>
          </div>
        </template>
      </DragDropTransfer>
    </FormField>

    <!-- Save bar -->
    <div class="flex items-center justify-between gap-2 border-t border-[var(--color-border)] pt-3">
      <p class="text-xs text-[var(--color-text-muted)]">
        <template v-if="isDirty">Unsaved changes</template>
        <template v-else>
          {{ assignedCount }}
          {{ assignedCount === 1 ? 'mapping' : 'mappings' }} saved
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
