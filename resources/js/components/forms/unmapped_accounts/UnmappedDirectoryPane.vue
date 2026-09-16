<script setup lang="ts">
/**
 * One unmapped account or branch, opened from the listing.
 *
 * Two tabs, because there are two questions a reader has about a coverage gap: what is
 * this thing, and who is sitting behind it. The record is handed in already fetched;
 * the members load when their tab is first opened, since an account here can hold ten
 * thousand cardholders and most readers came for the details.
 */
import { computed, h, ref, watch } from 'vue';
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
  type DirectoryRow,
  type DirectoryScope,
} from '@/composables/unmappedAccounts';
import { X } from 'lucide-vue-next';

const props = defineProps<{
  row: DirectoryRow;
  scope: DirectoryScope;
  code: string;
  detail: DirectoryDetail | null;
}>();

const { getDirectoryMembers } = useUnmappedAccounts();

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

/** Load on first open only; afterwards the tab keeps whatever page it was left on. */
watch(activeTab, (tab) => {
  if (tab === 'members' && !membersLoaded.value && !membersLoading.value) {
    void fetchMembers();
  }
});

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
    </Tabs>
  </div>
</template>
