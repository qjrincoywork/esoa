<script setup lang="ts">
/**
 * One unmapped account or branch, opened from the listing.
 *
 * Four tabs, because there are four questions a reader has about a coverage gap: what
 * is this thing, who is sitting behind it, what does it break down into, and — since
 * a search can widen to include what is already mapped — who already has it. The
 * record is handed in already fetched; everything else loads only when its own tab is
 * first opened, since an account here can hold ten thousand cardholders and most
 * readers came for one tab, not all four.
 */
import { computed, h, onBeforeUnmount, ref, watch, type Ref } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import Datatable from '@/components/Datatable.vue';
import TopPane from '@/components/TopPane.vue';
import DirectoryFactsList from '@/components/forms/unmapped_accounts/DirectoryFactsList.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  DIRECTORY_SCOPE,
  useUnmappedAccounts,
  type AccountDetail,
  type BranchDetail,
  type DirectoryDetail,
  type DirectoryMember,
  type DirectoryPage,
  type DirectoryRow,
  type DirectoryScope,
  type MappedUser,
} from '@/composables/unmappedAccounts';
import { badge, mappedStatusBadge, standingText } from '@/lib/directoryBadges';
import {
  accountFacts,
  branchFacts,
  branchRowFacts,
  mappedUserFacts,
  memberFacts,
  type Fact,
} from '@/lib/directoryFacts';
import { usePane } from '@/composables/usePane';
import { X } from 'lucide-vue-next';

const props = defineProps<{
  row: DirectoryRow;
  scope: DirectoryScope;
  code: string;
  detail: DirectoryDetail | null;
}>();

const { getDirectoryDetail, getDirectoryMembers, getAccountBranches, getMappedUsers } = useUnmappedAccounts();

const isBranch = computed(() => props.scope === DIRECTORY_SCOPE.BRANCH);
const account = computed(() => (isBranch.value ? null : (props.detail as AccountDetail | null)));
const branch = computed(() => (isBranch.value ? (props.detail as BranchDetail | null) : null));

const detailsTabLabel = computed(() => (isBranch.value ? 'Branch Details' : 'Account Details'));

/**
 * The count the listing showed, which is what the Members tab must add up to.
 * Taken from the record when it arrived, and from the row itself until it did.
 */
const memberCount = computed<number>(() => props.detail?.member_count ?? props.row.member_count ?? 0);

const facts = computed<Fact[]>(() => {
  if (branch.value) return branchFacts(branch.value);
  if (account.value) return accountFacts(account.value);

  return [];
});

/**
 * The heading badge: the standing the server decided (`App\Enums\AccountStanding`),
 * falling back to the listing row's until the record arrives. A branch shows its
 * account's, so the badge says whose it is.
 */
const standing = computed(() => props.detail?.standing ?? props.row.standing ?? null);
const standingLabel = computed(() => standingText(standing.value, isBranch.value ? 'Account' : ''));

const activeTab = ref('details');

type Pagination = { current_page: number; per_page: number; total: number };

/**
 * One lazily-loaded, paginated tab — the "Members", "Branches" and "Mapped Users" tabs
 * share this shape rather than each hand-rolling their own load/search/paginate
 * plumbing. Fetched only when its tab is first opened, and under its own token so a
 * stale response from a superseded search or page change can never overwrite a newer one.
 */
function useLazyTabList<T>(
  fetchPage: (params: Record<string, string | number>) => Promise<DirectoryPage<T>>,
  getSearchParams: () => Record<string, string | number>,
  errorMessage = 'Could not load this list.',
) {
  const data = ref([]) as Ref<T[]>;
  const loaded = ref(false);
  const loading = ref(false);
  const error = ref('');
  const pagination = ref<Pagination>({ current_page: 1, per_page: 10, total: 0 });
  const token = ref(0);
  let searchTimer: number | undefined;

  const load = async () => {
    const myToken = ++token.value;
    loading.value = true;
    error.value = '';

    try {
      const result = await fetchPage({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...getSearchParams(),
      });

      if (myToken !== token.value) return;

      data.value = result.data ?? [];
      pagination.value = {
        current_page: Number(result.current_page),
        per_page: Number(result.per_page),
        total: Number(result.total),
      };
      loaded.value = true;
    } catch {
      if (myToken !== token.value) return;
      data.value = [];
      error.value = errorMessage;
    } finally {
      if (myToken === token.value) loading.value = false;
    }
  };

  const reload = () => {
    pagination.value.current_page = 1;
    void load();
  };

  /** What the table's pager hands back: a new page, or a new page size (which starts over at page 1). */
  const changePage = (next: Pagination) => {
    pagination.value = { ...pagination.value, current_page: next.current_page, per_page: Number(next.per_page) };
    void load();
  };

  /** Re-fetches from page 1 once typing has settled, and only once the tab has been opened. */
  const debounceReload = (delay = 500) => () => {
    if (!loaded.value) return;
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(reload, delay);
  };

  /**
   * Forget everything fetched for the previous record: pending search, in-flight
   * response (its token no longer matches), rows and page. The tab then loads afresh
   * the next time it is shown.
   */
  const reset = () => {
    window.clearTimeout(searchTimer);
    token.value++;
    data.value = [];
    loaded.value = false;
    loading.value = false;
    error.value = '';
    pagination.value = { ...pagination.value, current_page: 1, total: 0 };
  };

  /** Load unless this tab already holds (or is fetching) the current record's rows. */
  const ensureLoaded = () => {
    if (!loaded.value && !loading.value) void load();
  };

  return { data, loaded, loading, error, pagination, load, reload, reset, ensureLoaded, changePage, debounceReload };
}

// ── Members ───────────────────────────────────────────────────────────────
const SEARCH_FIELDS = [
  { value: 'name', label: 'Name' },
  { value: 'policynum', label: 'Policy Number' },
] as const;

type MemberSearchField = (typeof SEARCH_FIELDS)[number]['value'];

const searchField = ref<MemberSearchField>('name');
const searchText = ref('');
const searchActive = computed(() => searchText.value.trim() !== '');

const membersList = useLazyTabList<DirectoryMember>(
  (params) => getDirectoryMembers(props.scope, props.code, params),
  () => {
    const term = searchText.value.trim();

    return term ? { [searchField.value]: term } : {};
  },
  'Could not load the members for this record.',
);
const {
  data: members,
  loading: membersLoading,
  error: membersError,
  pagination: memberPagination,
  changePage: changeMemberPage,
} = membersList;

watch([searchField, searchText], membersList.debounceReload());

const clearMemberSearch = () => {
  searchText.value = '';
};

const columnHelper = createColumnHelper<DirectoryMember>();
const memberColumns = [
  columnHelper.accessor('name', { header: 'Member', cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('policy_number', {
    header: 'Policy Number',
    cell: ({ getValue }) => h('span', { class: 'font-mono text-xs' }, getValue() || '—'),
  }),
  columnHelper.accessor('birth_date', { header: 'Birth Date', cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('plan_code', {
    header: 'Plan',
    cell: ({ getValue }) => h('span', { class: 'font-mono text-xs' }, getValue() || '—'),
  }),
  columnHelper.accessor('expiry_date', { header: 'Coverage Until', cell: ({ getValue }) => getValue() || '—' }),
];

// ── Branches (accounts only — a branch has no branches of its own) ─────────
const branchSearchText = ref('');
const branchesList = useLazyTabList<DirectoryRow>(
  (params) => getAccountBranches(props.code, params),
  () => {
    const params: Record<string, string | number> = {};
    const term = branchSearchText.value.trim();
    if (term) params.name = term;

    return params;
  },
);
const {
  data: branches,
  loading: branchesLoading,
  error: branchesError,
  pagination: branchPagination,
  changePage: changeBranchPage,
} = branchesList;

/** Known up front from the account detail, so the tab's count never waits on itself. */
const branchesTabLabel = computed(() => {
  const total = account.value?.branch_count;

  return total !== undefined ? `Branches (${total.toLocaleString()})` : 'Branches';
});

watch(branchSearchText, branchesList.debounceReload());

const clearBranchSearch = () => {
  branchSearchText.value = '';
};

// Reuses the same shape the main directory listing renders a branch row with — the
// "Branches" tab and the coverage-gap listing are answering the same question about
// the same rows, just scoped to one account instead of the whole directory.
const branchColumnHelper = createColumnHelper<DirectoryRow>();
const branchColumns = [
  branchColumnHelper.accessor('branch_name', { header: 'Branch', cell: (info: any) => info.getValue() || '—' }),
  branchColumnHelper.accessor('branch_code', {
    header: 'Branch Code',
    cell: (info: any) => h('span', { class: 'font-mono text-xs' }, info.getValue() || '—'),
  }),
  branchColumnHelper.accessor('member_count', {
    header: 'Members',
    cell: (info: any) => h('div', { class: 'text-right tabular-nums' }, Number(info.getValue() ?? 0).toLocaleString()),
  }),
  branchColumnHelper.accessor('mapped_users', {
    header: 'Mapping',
    cell: (info: any) => mappedStatusBadge(info.getValue()),
  }),
];

// ── Mapped Users (accounts and branches) ────────────────────────────────────
const mappedUserSearchText = ref('');
const mappedUsersList = useLazyTabList<MappedUser>(
  (params) => getMappedUsers(props.scope, props.code, params),
  () => {
    const params: Record<string, string | number> = {};
    const term = mappedUserSearchText.value.trim();
    if (term) params.search = term;

    return params;
  },
  'Could not load the users mapped to this record.',
);
const {
  data: mappedUsers,
  loaded: mappedUsersLoaded,
  loading: mappedUsersLoading,
  error: mappedUsersError,
  pagination: mappedUserPagination,
  changePage: changeMappedUserPage,
} = mappedUsersList;

/**
 * The row already carries `mapped_users` when the listing's "Include mapped" search
 * widened to it — shown as a hint before the tab has fetched its own, authoritative
 * count.
 */
const mappedUsersTabLabel = computed(() => {
  const total = mappedUsersLoaded.value
    ? mappedUserPagination.value.total
    : (props.row.mapped_users?.length ?? null);

  return total !== null ? `Mapped Users (${total.toLocaleString()})` : 'Mapped Users';
});

watch(mappedUserSearchText, mappedUsersList.debounceReload());

const clearMappedUserSearch = () => {
  mappedUserSearchText.value = '';
};

const mappedUserColumnHelper = createColumnHelper<MappedUser>();
const mappedUserColumns = [
  mappedUserColumnHelper.accessor('username', { header: 'User', cell: (info: any) => info.getValue() || '—' }),
  mappedUserColumnHelper.accessor('email', { header: 'Email', cell: (info: any) => info.getValue() || '—' }),
  mappedUserColumnHelper.accessor('is_active', {
    header: 'Status',
    cell: (info: any) => (info.getValue()
      ? badge('Active', 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400')
      : badge('Inactive', 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400')),
  }),
  mappedUserColumnHelper.accessor('mapped_in_full', {
    // For a branch, whether the mapping names it directly or reaches it by covering
    // the whole account; for an account, whether one mapping covers all its branches
    // or just the one named here.
    header: 'Coverage',
    cell: (info: any) => (info.getValue()
      ? badge('Whole account', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300')
      : h('span', { class: 'font-mono text-xs' }, info.row.original.branch_code || '—')),
  }),
  mappedUserColumnHelper.accessor('mapped_at', { header: 'Mapped', cell: (info: any) => info.getValue() || '—' }),
];

// ── Row details (top pane) ──────────────────────────────────────────────────
/**
 * Clicking a row of any list opens its full record in a top pane over this one. Each
 * list only says how to build that row's facts; opening, loading and errors are shared.
 * A token keeps a slow lookup from overwriting a row the reader has since clicked.
 */
const { openPane, closePane, setPaneLoading, setPaneError, setPaneContent, topPane } = usePane();
let rowPaneToken = 0;

const showRowPane = async (title: string, factsOf: () => Fact[] | Promise<Fact[]>) => {
  const token = ++rowPaneToken;
  openPane({ side: 'top', title, loading: true });

  try {
    const rowFacts = await factsOf();
    if (token !== rowPaneToken) return;
    setPaneContent('top', DirectoryFactsList, { facts: rowFacts });
  } catch {
    if (token !== rowPaneToken) return;
    setPaneError('top', 'Could not load the full details of this row.');
  } finally {
    if (token === rowPaneToken) setPaneLoading('top', false);
  }
};

const openMemberRow = (member: DirectoryMember) =>
  showRowPane(member.name || member.policy_number || 'Member', () => memberFacts(member));

const openMappedUserRow = (user: MappedUser) =>
  showRowPane(user.username || user.email || 'Mapped User', () => mappedUserFacts(user));

/**
 * A branch row carries only its name, code and counts; the full record is one more
 * lookup, cached per code so reopening a branch (or paging back to it) costs nothing.
 */
const branchDetails = new Map<string, Promise<DirectoryDetail | null>>();

const branchDetailOf = (code: string) => {
  if (!branchDetails.has(code)) {
    const pending = getDirectoryDetail(DIRECTORY_SCOPE.BRANCH, code);
    pending.catch(() => branchDetails.delete(code));
    branchDetails.set(code, pending);
  }

  return branchDetails.get(code)!;
};

const openBranchRow = (row: DirectoryRow) => {
  const code = String(row.branch_code ?? '');
  if (!code) return;

  void showRowPane(row.branch_name || code, async () => {
    const detail = (await branchDetailOf(code)) as BranchDetail | null;
    const base: Fact[] = detail
      ? branchFacts(detail)
      : [
          { label: 'Branch Code', value: code, mono: true },
          { label: 'Branch Name', value: row.branch_name },
        ];

    return [...base, ...branchRowFacts(row)];
  });
};

// The pane is global state; leaving it open would reopen it over the next record.
onBeforeUnmount(() => {
  if (topPane.open) closePane('top');
});

/** Each lazily-loaded tab, by its tab value. Adding a list tab means adding it here. */
const lazyTabs: Record<string, ReturnType<typeof useLazyTabList<any>>> = {
  members: membersList,
  branches: branchesList,
  mapped_users: mappedUsersList,
};

/** Load on first open only; afterwards each tab keeps whatever page it was left on. */
watch(activeTab, (tab) => lazyTabs[tab]?.ensureLoaded());

/**
 * The pane host reuses this component when another row is opened while it is still
 * showing — only the props change, so nothing would refetch and every tab would keep
 * the previous record's rows (most visibly an empty "Mapped Users" carried over from an
 * unmapped row). Starting over on a new record, and reloading the tab the reader is on,
 * keeps each tab about the row actually opened.
 */
watch(
  () => [props.scope, props.code] as const,
  () => {
    Object.values(lazyTabs).forEach((list) => list.reset());
    searchText.value = '';
    branchSearchText.value = '';
    mappedUserSearchText.value = '';
    branchDetails.clear();
    if (topPane.open) closePane('top');

    // A branch has no "Branches" tab, so a reader left on it goes back to the details.
    if (!(activeTab.value in lazyTabs) || (isBranch.value && activeTab.value === 'branches')) {
      activeTab.value = 'details';
      return;
    }
    lazyTabs[activeTab.value].ensureLoaded();
  },
);
</script>

<template>
  <div class="flex w-full flex-col gap-3">
    <!-- The heading facts, so the pane says what it is before any tab is chosen. -->
    <div class="flex flex-wrap items-center gap-2">
      <span
        v-if="standing"
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="standing.color">
        {{ standingLabel }}
      </span>
      <span
        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
        {{ row.account_type_label }}
      </span>
      <span class="font-mono text-xs text-[var(--color-text-muted)]">{{ code }}</span>
    </div>

    <Tabs v-model="activeTab" default-value="details">
      <TabsList>
        <TabsTrigger class="cursor-pointer" value="details">
          {{ detailsTabLabel }}
        </TabsTrigger>
        <TabsTrigger class="cursor-pointer" value="members">
          Members ({{ memberCount.toLocaleString() }})
        </TabsTrigger>
        <TabsTrigger v-if="!isBranch" class="cursor-pointer" value="branches">
          {{ branchesTabLabel }}
        </TabsTrigger>
        <TabsTrigger class="cursor-pointer" value="mapped_users">
          {{ mappedUsersTabLabel }}
        </TabsTrigger>
      </TabsList>

      <!-- Details -->
      <TabsContent value="details" class="mt-3">
        <div
          v-if="!detail"
          class="rounded-md border border-[var(--color-border)] px-3 py-2 text-sm text-[var(--color-text-muted)]">
          This record could not be read from the directory.
        </div>
        <DirectoryFactsList v-else :facts="facts" />
      </TabsContent>

      <!-- Members -->
      <TabsContent value="members" class="mt-3 flex flex-col gap-3">
        <p class="text-xs text-[var(--color-text-muted)]">
          The {{ memberCount.toLocaleString() }}
          {{ memberCount === 1 ? 'cardholder' : 'cardholders' }} counted against this
          {{ isBranch ? 'branch' : 'account' }} on the listing. Click a row for its full details.
        </p>

        <div class="flex flex-wrap items-center gap-2">
          <Select v-model="searchField">
            <SelectTrigger class="h-8 w-36 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup>
                <SelectItem
                  v-for="field in SEARCH_FIELDS"
                  :key="field.value"
                  :value="field.value"
                  class="text-xs">
                  {{ field.label }}
                </SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>

          <div class="relative min-w-0 flex-1">
            <label class="sr-only" for="member-search">Search members</label>
            <input
              id="member-search"
              v-model="searchText"
              type="text"
              placeholder="Search members..."
              class="h-8 w-full rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface)] px-3 pr-8 text-xs text-[var(--color-text)] focus:border-transparent focus:ring-2 focus:ring-opacity-50"
              :style="{ '--tw-ring-color': 'var(--primary-color)' }" />
            <button
              v-if="searchActive"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none"
              aria-label="Clear member search"
              @click="clearMemberSearch">
              <X class="h-3.5 w-3.5" />
            </button>
          </div>

          <Button
            v-if="searchActive"
            variant="ghost"
            size="sm"
            class="h-8 px-2 text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
            @click="clearMemberSearch">
            Clear
          </Button>
        </div>

        <Datatable
          :data="members"
          :columns="memberColumns"
          :pagination="memberPagination"
          :loading="membersLoading"
          :error="membersError"
          :enable-search="false"
          empty-message="No members found"
          empty-description="Nobody is recorded against this record in the directory."
          :export-file-name="`members_${code}`"
          enable-row-click
          :row-click="openMemberRow"
          @update:pagination="changeMemberPage" />
      </TabsContent>

      <!-- Branches (accounts only) -->
      <TabsContent v-if="!isBranch" value="branches" class="mt-3 flex flex-col gap-3">
        <p class="text-xs text-[var(--color-text-muted)]">
          The HMS branches recorded under this account. Click a row for its full details.
        </p>

        <div class="flex flex-wrap items-center gap-2">
          <div class="relative min-w-0 flex-1">
            <label class="sr-only" for="branch-search">Search branches</label>
            <input
              id="branch-search"
              v-model="branchSearchText"
              type="text"
              placeholder="Search branches..."
              class="h-8 w-full rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface)] px-3 pr-8 text-xs text-[var(--color-text)] focus:border-transparent focus:ring-2 focus:ring-opacity-50"
              :style="{ '--tw-ring-color': 'var(--primary-color)' }" />
            <button
              v-if="branchSearchText"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none"
              aria-label="Clear branch search"
              @click="clearBranchSearch">
              <X class="h-3.5 w-3.5" />
            </button>
          </div>

          <Button
            v-if="branchSearchText"
            variant="ghost"
            size="sm"
            class="h-8 px-2 text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
            @click="clearBranchSearch">
            Clear
          </Button>
        </div>

        <Datatable
          :data="branches"
          :columns="branchColumns"
          :pagination="branchPagination"
          :loading="branchesLoading"
          :error="branchesError"
          :enable-search="false"
          empty-message="No branches found"
          empty-description="This account has no branches recorded in the directory."
          :export-file-name="`branches_${code}`"
          enable-row-click
          :row-click="openBranchRow"
          @update:pagination="changeBranchPage" />
      </TabsContent>

      <!-- Mapped Users -->
      <TabsContent value="mapped_users" class="mt-3 flex flex-col gap-3">
        <p class="text-xs text-[var(--color-text-muted)]">
          The users already mapped to this {{ isBranch ? 'branch' : 'account' }}. Click a row for its full details.
        </p>

        <div class="flex flex-wrap items-center gap-2">
          <div class="relative min-w-0 flex-1">
            <label class="sr-only" for="mapped-user-search">Search mapped users</label>
            <input
              id="mapped-user-search"
              v-model="mappedUserSearchText"
              type="text"
              placeholder="Search by username or email..."
              class="h-8 w-full rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface)] px-3 pr-8 text-xs text-[var(--color-text)] focus:border-transparent focus:ring-2 focus:ring-opacity-50"
              :style="{ '--tw-ring-color': 'var(--primary-color)' }" />
            <button
              v-if="mappedUserSearchText"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none"
              aria-label="Clear mapped user search"
              @click="clearMappedUserSearch">
              <X class="h-3.5 w-3.5" />
            </button>
          </div>

          <Button
            v-if="mappedUserSearchText"
            variant="ghost"
            size="sm"
            class="h-8 px-2 text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
            @click="clearMappedUserSearch">
            Clear
          </Button>
        </div>

        <Datatable
          :data="mappedUsers"
          :columns="mappedUserColumns"
          :pagination="mappedUserPagination"
          :loading="mappedUsersLoading"
          :error="mappedUsersError"
          :enable-search="false"
          empty-message="No mapped users found"
          empty-description="Nobody has been given this record yet."
          :export-file-name="`mapped_users_${code}`"
          enable-row-click
          :row-click="openMappedUserRow"
          @update:pagination="changeMappedUserPage" />
      </TabsContent>
    </Tabs>

    <TopPane
      :open="topPane.open"
      :title="topPane.title"
      :loading="topPane.loading"
      :error="topPane.error"
      :content-component="topPane.contentComponent"
      :component-props="topPane.componentProps"
      @update:open="(v) => { if (!v) closePane('top') }" />
  </div>
</template>
