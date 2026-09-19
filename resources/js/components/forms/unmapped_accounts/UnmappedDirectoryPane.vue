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
import { computed, h, ref, watch, type Ref } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import Datatable from '@/components/Datatable.vue';
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
import { badge, mappedStatusBadge } from '@/lib/directoryBadges';
import { X } from 'lucide-vue-next';

const props = defineProps<{
  row: DirectoryRow;
  scope: DirectoryScope;
  code: string;
  detail: DirectoryDetail | null;
}>();

const { getDirectoryMembers, getAccountBranches, getMappedUsers } = useUnmappedAccounts();

const isBranch = computed(() => props.scope === DIRECTORY_SCOPE.BRANCH);
const account = computed(() => (isBranch.value ? null : (props.detail as AccountDetail | null)));
const branch = computed(() => (isBranch.value ? (props.detail as BranchDetail | null) : null));

const detailsTabLabel = computed(() => (isBranch.value ? 'Branch Details' : 'Account Details'));

/**
 * The count the listing showed, which is what the Members tab must add up to.
 * Taken from the record when it arrived, and from the row itself until it did.
 */
const memberCount = computed<number>(() => props.detail?.member_count ?? props.row.member_count ?? 0);

/** A labelled row of the detail list; blanks are shown as an em dash rather than hidden. */
type Fact = { label: string; value: string | null | undefined; mono?: boolean };

const accountFacts = computed<Fact[]>(() => {
  const a = account.value;
  if (!a) return [];

  return [
    { label: 'Account Code', value: a.account_code, mono: true },
    { label: 'Account Name', value: a.account_name },
    { label: 'Main Account', value: a.main_account_code, mono: true },
    { label: 'Code Prefix', value: a.code_prefix, mono: true },
    { label: 'Type', value: a.account_type_label },
    { label: 'HMS Account Type', value: a.hms_account_type, mono: true },
    { label: 'Branches', value: a.branch_count.toLocaleString() },
    { label: 'TIN', value: a.tin, mono: true },
    { label: 'Address', value: a.address },
    { label: 'Contact Person', value: a.contact_person },
    { label: 'Contact Number', value: a.contact_number },
    { label: 'Agent Code', value: a.agent_code, mono: true },
    { label: 'Effectivity Date', value: a.effectivity_date },
    { label: 'Renewal Date', value: a.renewal_date },
    { label: 'Expiry Date', value: a.expiry_date },
    { label: 'Cancel Date', value: a.cancel_date },
    { label: 'Cancel Reason', value: a.cancel_reason },
  ];
});

const branchFacts = computed<Fact[]>(() => {
  const b = branch.value;
  if (!b) return [];

  return [
    { label: 'Branch Code', value: b.branch_code, mono: true },
    { label: 'Branch Name', value: b.branch_name },
    { label: 'Account Code', value: b.account_code, mono: true },
    { label: 'Account Name', value: b.account_name },
    { label: 'Main Account', value: b.main_account_code, mono: true },
    { label: 'Code Prefix', value: b.code_prefix, mono: true },
    { label: 'Type', value: b.account_type_label },
    { label: 'TIN', value: b.tin, mono: true },
    { label: 'Address', value: b.address },
    { label: 'Attention', value: b.attention },
    { label: 'Position', value: b.position },
  ];
});

const facts = computed<Fact[]>(() => (isBranch.value ? branchFacts.value : accountFacts.value));

/**
 * Whether the thing is still in force. A branch has no status of its own — it is in
 * force exactly when its account is, which is what the listing filters on too.
 */
const isActive = computed<boolean>(() =>
  isBranch.value ? (branch.value?.account_is_active ?? false) : (account.value?.is_active ?? false),
);

const activeLabel = computed(() =>
  isBranch.value
    ? (isActive.value ? 'Account active' : 'Account inactive')
    : (isActive.value ? 'Active' : 'Inactive'),
);

// ── Members ───────────────────────────────────────────────────────────────
const activeTab = ref('details');
const members = ref<DirectoryMember[]>([]);
const membersLoaded = ref(false);
const membersLoading = ref(false);
const membersError = ref('');
const memberPagination = ref({ current_page: 1, per_page: 10, total: 0 });

const SEARCH_FIELDS = [
  { value: 'name', label: 'Name' },
  { value: 'policynum', label: 'Policy Number' },
] as const;

type MemberSearchField = (typeof SEARCH_FIELDS)[number]['value'];

const searchField = ref<MemberSearchField>('name');
const searchText = ref('');
const searchActive = computed(() => searchText.value.trim() !== '');

/**
 * Guards against an out-of-order response: a reader who types quickly fires several
 * lookups, and only the newest one may write to the list.
 */
const fetchToken = ref(0);

const fetchMembers = async () => {
  const token = ++fetchToken.value;
  membersLoading.value = true;
  membersError.value = '';

  const params: Record<string, string | number> = {
    page: memberPagination.value.current_page,
    per_page: memberPagination.value.per_page,
  };

  const term = searchText.value.trim();
  if (term) params[searchField.value] = term;

  try {
    const result = await getDirectoryMembers(props.scope, props.code, params);

    if (token !== fetchToken.value) return;

    members.value = result.data ?? [];
    memberPagination.value = {
      current_page: result.current_page,
      per_page: Number(result.per_page),
      total: result.total,
    };
    membersLoaded.value = true;
  } catch {
    if (token !== fetchToken.value) return;
    members.value = [];
    membersError.value = 'Could not load the members for this record.';
  } finally {
    if (token === fetchToken.value) membersLoading.value = false;
  }
};

const searchTimeout = ref<number | null>(null);
watch([searchField, searchText], () => {
  if (!membersLoaded.value) return;
  if (searchTimeout.value) clearTimeout(searchTimeout.value);

  searchTimeout.value = window.setTimeout(() => {
    memberPagination.value.current_page = 1;
    void fetchMembers();
  }, 500);
});

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

/**
 * One lazily-loaded, paginated tab — the "Branches" and "Mapped Users" tabs share this
 * shape rather than each hand-rolling their own load/search/paginate plumbing. Fetched
 * only when its tab is first opened, and under its own token so a stale response from
 * a superseded search or page change can never overwrite a newer one.
 */
function useLazyTabList<T>(
  fetchPage: (params: Record<string, string | number>) => Promise<DirectoryPage<T>>,
  getSearchParams: () => Record<string, string | number>,
) {
  const data = ref([]) as Ref<T[]>;
  const loaded = ref(false);
  const loading = ref(false);
  const error = ref('');
  const pagination = ref({ current_page: 1, per_page: 10, total: 0 });
  const token = ref(0);

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
        current_page: result.current_page,
        per_page: Number(result.per_page),
        total: result.total,
      };
      loaded.value = true;
    } catch {
      if (myToken !== token.value) return;
      data.value = [];
      error.value = 'Could not load this list.';
    } finally {
      if (myToken === token.value) loading.value = false;
    }
  };

  const reload = () => {
    pagination.value.current_page = 1;
    void load();
  };

  return { data, loaded, loading, error, pagination, load, reload };
}

// ── Branches (accounts only — a branch has no branches of its own) ─────────
const branchSearchText = ref('');
const {
  data: branches,
  loaded: branchesLoaded,
  loading: branchesLoading,
  error: branchesError,
  pagination: branchPagination,
  load: loadBranches,
  reload: reloadBranches,
} = useLazyTabList<DirectoryRow>(
  (params) => getAccountBranches(props.code, params),
  () => {
    const params: Record<string, string | number> = {};
    const term = branchSearchText.value.trim();
    if (term) params.name = term;

    return params;
  },
);

/** Known up front from the account detail, so the tab's count never waits on itself. */
const branchesTabLabel = computed(() => {
  const total = account.value?.branch_count;

  return total !== undefined ? `Branches (${total.toLocaleString()})` : 'Branches';
});

const branchSearchTimeout = ref<number | null>(null);
watch(branchSearchText, () => {
  if (!branchesLoaded.value) return;
  if (branchSearchTimeout.value) clearTimeout(branchSearchTimeout.value);

  branchSearchTimeout.value = window.setTimeout(() => reloadBranches(), 500);
});

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
const {
  data: mappedUsers,
  loaded: mappedUsersLoaded,
  loading: mappedUsersLoading,
  error: mappedUsersError,
  pagination: mappedUserPagination,
  load: loadMappedUsers,
  reload: reloadMappedUsers,
} = useLazyTabList<MappedUser>(
  (params) => getMappedUsers(props.scope, props.code, params),
  () => {
    const params: Record<string, string | number> = {};
    const term = mappedUserSearchText.value.trim();
    if (term) params.search = term;

    return params;
  },
);

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

const mappedUserSearchTimeout = ref<number | null>(null);
watch(mappedUserSearchText, () => {
  if (!mappedUsersLoaded.value) return;
  if (mappedUserSearchTimeout.value) clearTimeout(mappedUserSearchTimeout.value);

  mappedUserSearchTimeout.value = window.setTimeout(() => reloadMappedUsers(), 500);
});

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

/** Load on first open only; afterwards each tab keeps whatever page it was left on. */
watch(activeTab, (tab) => {
  if (tab === 'members' && !membersLoaded.value && !membersLoading.value) {
    void fetchMembers();
  }
  if (tab === 'branches' && !branchesLoaded.value && !branchesLoading.value) {
    void loadBranches();
  }
  if (tab === 'mapped_users' && !mappedUsersLoaded.value && !mappedUsersLoading.value) {
    void loadMappedUsers();
  }
});
</script>

<template>
  <div class="flex w-full flex-col gap-3">
    <!-- The heading facts, so the pane says what it is before any tab is chosen. -->
    <div class="flex flex-wrap items-center gap-2">
      <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="isActive
          ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
          : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'">
        {{ activeLabel }}
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
        <dl v-else class="divide-y divide-[var(--color-border)] text-sm">
          <div v-for="fact in facts" :key="fact.label" class="grid grid-cols-3 gap-3 py-2">
            <dt class="text-[var(--color-text-muted)]">{{ fact.label }}</dt>
            <dd class="col-span-2 break-words" :class="fact.mono ? 'font-mono text-xs' : ''">
              {{ fact.value || '—' }}
            </dd>
          </div>
        </dl>
      </TabsContent>

      <!-- Members -->
      <TabsContent value="members" class="mt-3 flex flex-col gap-3">
        <p class="text-xs text-[var(--color-text-muted)]">
          The {{ memberCount.toLocaleString() }}
          {{ memberCount === 1 ? 'cardholder' : 'cardholders' }} counted against this
          {{ isBranch ? 'branch' : 'account' }} on the listing.
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
          @update:pagination="(next: typeof memberPagination) => { memberPagination = next; fetchMembers() }" />
      </TabsContent>

      <!-- Branches (accounts only) -->
      <TabsContent v-if="!isBranch" value="branches" class="mt-3 flex flex-col gap-3">
        <p class="text-xs text-[var(--color-text-muted)]">
          The HMS branches recorded under this account.
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
          @update:pagination="(next: typeof branchPagination) => { branchPagination = next; loadBranches() }" />
      </TabsContent>

      <!-- Mapped Users -->
      <TabsContent value="mapped_users" class="mt-3 flex flex-col gap-3">
        <p class="text-xs text-[var(--color-text-muted)]">
          The users already mapped to this {{ isBranch ? 'branch' : 'account' }}.
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
          @update:pagination="(next: typeof mappedUserPagination) => { mappedUserPagination = next; loadMappedUsers() }" />
      </TabsContent>
    </Tabs>
  </div>
</template>
