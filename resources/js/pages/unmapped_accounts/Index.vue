<script setup lang="ts">
/**
 * The account-mapping coverage gap: accounts and branches nobody has been given.
 *
 * Read-only by design — nothing is mapped from here. This is the question the user
 * screens cannot answer: a per-user view lists what someone has, so an account nobody
 * is mapped to appears on nobody's screen. Narrow it by name, by code class, by
 * account type or by how many members are sitting behind the gap, then go and map it
 * on the user it belongs to.
 *
 * Accounts and branches are views of one listing rather than two pages: the filters
 * mean the same thing in each, so switching tabs keeps them. The first view lists both
 * kinds together, so a name or code — or a pasted list of them, through the bulk
 * search — is found wherever it is, without first guessing which half it lives in. Only
 * the view being looked at is fetched, and paged by the server: the directory is tens
 * of thousands of rows on a remote database.
 */
import { computed, h, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { createColumnHelper, type ColumnDef } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import BulkSearch from '@/components/BulkSearch.vue';
import Datatable from '@/components/Datatable.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import ListSearch from '@/components/ListSearch.vue';
import RightPane from '@/components/RightPane.vue';
import MappingBadge from '@/components/forms/users/MappingBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useBulkLookup, type SearchTermMatch } from '@/composables/datatable/useBulkLookup';
import { useServerListing, type ListingPage } from '@/composables/datatable/useServerListing';
import { useModulePermissions } from '@/composables/useModulePermissions';
import {
    DIRECTORY_SCOPE,
    DIRECTORY_VIEW,
    useUnmappedAccounts,
    type DirectoryRow,
    type DirectoryScope,
    type DirectoryView,
} from '@/composables/unmappedAccounts';
import { badge, mappedStatusBadge, standingBadge } from '@/lib/directoryBadges';
import { cn } from '@/lib/utils';
import { SlidersHorizontal, TextSearch, X } from 'lucide-vue-next';

type DirectoryPagination = ListingPage & { data: DirectoryRow[] }

type Option = { value: string | number; name: string }

/** What `UnmappedAccountController::index` sends. */
interface UnmappedPageProps {
    directory?: DirectoryPagination
    scope?: DirectoryView
    search_term_matches?: SearchTermMatch[]
    max_search_terms?: number
    filter_options?: { scopes?: Option[]; code_prefixes?: Option[]; account_types?: Option[]; statuses?: Option[] }
}

/** Everything the page says differently per view, in one place rather than a ternary per label. */
const VIEW_COPY: Record<DirectoryView, { one: string; many: string; placeholder: string }> = {
    [DIRECTORY_VIEW.ALL]: { one: 'account or branch', many: 'accounts and branches', placeholder: 'Search account or branch name or code...' },
    [DIRECTORY_VIEW.ACCOUNT]: { one: 'account', many: 'accounts', placeholder: 'Search account name or code...' },
    [DIRECTORY_VIEW.BRANCH]: { one: 'branch', many: 'branches', placeholder: 'Search branch, account or code...' },
};

const page = usePage();
const pageProps = computed(() => page.props as unknown as UnmappedPageProps);
const { slug } = useModulePermissions();
const {
    openDirectoryRow,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
} = useUnmappedAccounts();

const EMPTY_PAGE: DirectoryPagination = { current_page: 1, per_page: 10, total: 0, data: [] };

const directory = computed(() => pageProps.value.directory ?? EMPTY_PAGE);

const scopes = computed(() => pageProps.value.filter_options?.scopes ?? []);

/**
 * Two readings of the same thing, on purpose.
 *
 * `scope` is what the reader just clicked — the tab highlights immediately and the
 * next request carries it. `renderedScope` is what the rows on screen actually are,
 * and the table reads that: the fetch is a round trip to a remote directory, and
 * labelling account rows with branch columns in the meantime would show empty cells
 * for as long as it takes. Both land together, because the scope prop travels with
 * the listing on every partial reload.
 */
const scope = ref<DirectoryView>(pageProps.value.scope ?? DIRECTORY_VIEW.ALL);
const renderedScope = computed<DirectoryView>(() => pageProps.value.scope ?? scope.value);
const isBranchScope = computed(() => renderedScope.value === DIRECTORY_VIEW.BRANCH);

/** What the rows are called, so every label follows the tab: "3 unmapped branches". */
const viewCopy = computed(() => VIEW_COPY[renderedScope.value] ?? VIEW_COPY[DIRECTORY_VIEW.ALL]);
const rowNoun = computed(() => ({ one: viewCopy.value.one, many: viewCopy.value.many }));

const searchQuery = ref('');

// --- Bulk lookup ("Search multiple accounts or branches") ---
const {
    lookup: bulkLookup,
    open: bulkSearchOpen,
    params: bulkLookupParams,
    visit: bulkLookupVisit,
    run: runBulkLookup,
    focus: focusBulkTerm,
    clear: clearBulkLookup,
    toggle: toggleBulkSearch,
    markStale: markMatchesStale,
} = useBulkLookup({ reload: (delay) => queueFetch(delay), search: searchQuery });

const searchTermMatches = computed(() => pageProps.value.search_term_matches ?? []);
const maxSearchTerms = computed(() => Number(pageProps.value.max_search_terms) || 100);

// --- Filters ---
type FilterKey = 'code_prefix' | 'account_type' | 'is_active'

/** One dropdown of the filter bar: what it narrows, how "no filter" reads, and its choices. */
interface FilterDef {
    key: FilterKey
    label: string
    allLabel: string
    options: { value: string; label: string }[]
    width: string
}

const emptyFilters = () => ({ code_prefix: '', account_type: '', is_active: '', members_min: '', members_max: '' });
const filters = ref(emptyFilters());

/**
 * Off by default, so the listing stays the coverage gap it's named for: only what
 * nobody has. Switched on, the search widens to also match what is already mapped —
 * from `user_accounts` — so a code that looks missing can be confirmed as taken rather
 * than left ambiguous.
 */
const includeMapped = ref(false);

const toSelectOptions = (options: Option[] = []) => options.map(({ value, name }) => ({ value: String(value), label: name }));

const filterDefs = computed<FilterDef[]>(() => {
    const options = pageProps.value.filter_options;

    return [
        { key: 'code_prefix', label: 'Account code prefix', allLabel: 'All code prefixes', options: toSelectOptions(options?.code_prefixes), width: 'w-44' },
        { key: 'account_type', label: 'Account type', allLabel: 'All account types', options: toSelectOptions(options?.account_types), width: 'w-44' },
        // A branch has no status of its own, so for a branch this asks about its account.
        { key: 'is_active', label: isBranchScope.value ? 'Account status' : 'Status', allLabel: 'Any status', options: toSelectOptions(options?.statuses), width: 'w-36' },
    ];
});

// '' means "no bound"; 0 is a real bound, so these cannot be falsy tests. The inputs are
// numeric, so a bound arrives as a number and only an empty one as ''.
const hasMembersMin = computed(() => String(filters.value.members_min) !== '');
const hasMembersMax = computed(() => String(filters.value.members_max) !== '');

/** A maximum under the minimum, which the server refuses (`gte:members_min`) — caught here instead. */
const membersRangeInvalid = computed(() =>
    hasMembersMin.value && hasMembersMax.value && Number(filters.value.members_max) < Number(filters.value.members_min));

/** Filters that narrow the listing; a members range is one filter, however many of its ends are set. */
const narrowingCount = computed(() =>
    filterDefs.value.filter(({ key }) => filters.value[key] !== '').length + (hasMembersMin.value || hasMembersMax.value ? 1 : 0));

const activeFilterCount = computed(() => narrowingCount.value + (includeMapped.value ? 1 : 0));

/**
 * The members range box, styled like the other filter controls: tinted while a bound is
 * set, red while the bounds are the wrong way round. Merged rather than stacked, since
 * the state classes replace base ones that would otherwise win on stylesheet order.
 */
const membersControlClass = computed(() => cn(
    'flex h-9 items-center rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30',
    (hasMembersMin.value || hasMembersMax.value) && 'border-primary/40 bg-primary/5 dark:bg-primary/10',
    membersRangeInvalid.value && 'border-destructive focus-within:border-destructive focus-within:ring-destructive/30',
));

const clearFilters = () => {
    filters.value = emptyFilters();
    includeMapped.value = false;
};

// --- Summary & empty state ---
const isNarrowed = computed(() => searchQuery.value.trim() !== '' || bulkLookup.value !== null || narrowingCount.value > 0);

const resultSummary = computed(() => {
    const total = directory.value.total;
    const qualifiers = [isNarrowed.value && 'matching', !includeMapped.value && 'unmapped'].filter(Boolean).join(' ');

    return `${total.toLocaleString()} ${qualifiers} ${total === 1 ? rowNoun.value.one : rowNoun.value.many}`.replace(/\s+/g, ' ');
});

const emptyState = computed(() => {
    if (bulkLookup.value) {
        return {
            message: `No ${rowNoun.value.many} match these entries`,
            description: [
                bulkLookup.value.exact ? 'Check the entries for typos, or turn off exact match.' : 'Check the entries for typos.',
                !includeMapped.value && 'Include mapped rows to see the ones already assigned.',
            ].filter(Boolean).join(' '),
        };
    }

    if (includeMapped.value) {
        return { message: `No ${rowNoun.value.many} found`, description: 'Nothing in the HMS directory matches these filters.' };
    }

    return {
        message: `No unmapped ${rowNoun.value.many} found`,
        description: `Every ${rowNoun.value.one} matching these filters is already assigned to a user.`,
    };
});

const exportFileName = computed(() => `unmapped_${rowNoun.value.many.replace(/\s+/g, '_')}`);

// --- Columns ---
const DASH = '—';

const TYPE_CLASSES: Record<string, string> = {
    T: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
    H: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
};
const NEUTRAL_TYPE = 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';

/**
 * A name with its code beneath it: the name is what gets read, the code what gets
 * mapped. One column rather than two — and no separate prefix column, since the code
 * starts with it.
 */
const nameWithCode = (name?: string | null, code?: string | null) => h('div', { class: 'min-w-0 max-w-80' }, [
    h('div', { class: 'truncate font-medium' }, name || DASH),
    h('div', { class: 'font-mono text-muted-foreground' }, code || DASH),
]);

const typeCell = (row: DirectoryRow) => badge(row.account_type_label ?? DASH, TYPE_CLASSES[row.account_type ?? ''] ?? NEUTRAL_TYPE);

/**
 * The standing as the server decides and colours it (`AccountStanding`), with the expiry
 * it turns on beneath it — muted, so the badge alone carries the colour.
 */
const standingCell = (row: DirectoryRow) => h('div', { class: 'flex flex-col items-start gap-0.5' }, [
    standingBadge(row.standing),
    row.expiry_date
        ? h('span', { class: 'whitespace-nowrap text-muted-foreground' }, `${row.standing?.value === 'expired' ? 'since' : 'expires'} ${row.expiry_date}`)
        : null,
]);

/**
 * Right-aligned and grouped: these run to five figures and are read as magnitudes. An
 * empty one is muted, so the gaps with members behind them stand out down the column.
 */
const memberCell = (count: number) => h(
    'div',
    { class: ['text-right tabular-nums', !count && 'text-muted-foreground'] },
    Number(count ?? 0).toLocaleString(),
);

const columnHelper = createColumnHelper<DirectoryRow>();

/**
 * Standing, type and members. For a branch the standing is its account's — a branch has
 * none of its own — so on the branch view the header says so rather than every row.
 */
const sharedColumns = (view: DirectoryView): ColumnDef<DirectoryRow, any>[] => [
    columnHelper.accessor('account_type_label', {
        header: 'Type',
        cell: ({ row }) => typeCell(row.original),
    }),
    columnHelper.accessor((row) => row.standing?.label ?? DASH, {
        id: 'standing',
        header: view === DIRECTORY_VIEW.BRANCH ? 'Account status' : 'Status',
        cell: ({ row }) => standingCell(row.original),
    }),
    columnHelper.accessor('member_count', {
        header: 'Members',
        cell: (info) => memberCell(info.getValue()),
    }),
];

const isBranchRow = (row: DirectoryRow) => row.kind === DIRECTORY_SCOPE.BRANCH;

/** What the row is: a branch by its own name and code, an account by its own. */
const ownNameWithCode = (row: DirectoryRow) => isBranchRow(row)
    ? nameWithCode(row.branch_name, row.branch_code)
    : nameWithCode(row.account_name, row.account_code);

/**
 * Where kinds mix, the row's name leads with the badge the mapping pickers give it —
 * mapping an account grants every branch, so which one a row is decides what mapping
 * it would take.
 */
const kindNameCell = (row: DirectoryRow) => h('div', { class: 'flex items-start gap-2' }, [
    h('span', { class: 'mt-0.5' }, [h(MappingBadge, { badge: row.kind_badge })]),
    ownNameWithCode(row),
]);

const accountColumns: ColumnDef<DirectoryRow, any>[] = [
    columnHelper.accessor('account_name', {
        header: 'Account',
        cell: ({ row }) => nameWithCode(row.original.account_name, row.original.account_code),
    }),
    ...sharedColumns(DIRECTORY_VIEW.ACCOUNT),
];

const branchColumns: ColumnDef<DirectoryRow, any>[] = [
    columnHelper.accessor((row) => row.branch_name ?? '', {
        id: 'branch_name',
        header: 'Branch',
        cell: ({ row }) => nameWithCode(row.original.branch_name, row.original.branch_code),
    }),
    columnHelper.accessor('account_name', {
        header: 'Account',
        cell: ({ row }) => nameWithCode(row.original.account_name, row.original.account_code),
    }),
    ...sharedColumns(DIRECTORY_VIEW.BRANCH),
];

/**
 * Both kinds in one table: the row's own name first, then — for a branch — whose branch
 * it is. An account row is its own account, so that cell stays empty rather than
 * repeating the name beside it.
 */
const allColumns: ColumnDef<DirectoryRow, any>[] = [
    columnHelper.accessor((row) => (isBranchRow(row) ? row.branch_name : row.account_name) ?? '', {
        id: 'name',
        header: 'Account / Branch',
        cell: ({ row }) => kindNameCell(row.original),
    }),
    columnHelper.accessor((row) => (isBranchRow(row) ? row.account_name : ''), {
        id: 'owner_account',
        header: 'Belongs to',
        cell: ({ row }) => isBranchRow(row.original)
            ? nameWithCode(row.original.account_name, row.original.account_code)
            : h('span', { class: 'text-muted-foreground' }, DASH),
    }),
    ...sharedColumns(DIRECTORY_VIEW.ALL),
];

const COLUMNS_BY_VIEW: Record<DirectoryView, ColumnDef<DirectoryRow, any>[]> = {
    [DIRECTORY_VIEW.ALL]: allColumns,
    [DIRECTORY_VIEW.ACCOUNT]: accountColumns,
    [DIRECTORY_VIEW.BRANCH]: branchColumns,
};

/** Appended only while `includeMapped` is on — otherwise every row would read "Unmapped". */
const mappedColumn = columnHelper.accessor((row) => row.mapped_users ?? [], {
    id: 'mapped_users',
    header: 'Mapping',
    cell: (info) => mappedStatusBadge(info.getValue()),
});

const columns = computed(() => {
    const base = COLUMNS_BY_VIEW[renderedScope.value] ?? allColumns;

    return includeMapped.value ? [...base, mappedColumn] : base;
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Unmapped Accounts',
        href: slug.value,
    },
];

// --- Fetching ---
const filterParams = (): Record<string, string> => {
    const params: Record<string, string> = { scope: scope.value };

    if (searchQuery.value.trim()) params.search_string = searchQuery.value.trim();
    Object.assign(params, bulkLookupParams());
    for (const [key, value] of Object.entries(filters.value)) {
        if (String(value) !== '') params[key] = String(value);
    }
    if (includeMapped.value) params.include_mapped = '1';

    return params;
};

const { pagination, isFetching, queueFetch, onPaginationChange } = useServerListing({
    listing: () => directory.value,
    url: () => `/${slug.value}`,
    params: filterParams,
    // The per-entry counts ride along only when they may have changed (useBulkLookup).
    visit: () => bulkLookupVisit(['directory', 'scope']),
});

const isView = (value: unknown): value is DirectoryView =>
    Object.values(DIRECTORY_VIEW).includes(value as DirectoryView);

/**
 * Switching view starts the listing over — page 3 of accounts is not page 3 of
 * branches — and recounts a bulk lookup's entries, which count different rows per view.
 */
const switchScope = (next: string | number | undefined) => {
    if (!isView(next) || next === scope.value) return;

    scope.value = next;
    markMatchesStale();
    queueFetch(0);
};

// Typing reloads once it pauses; spacing alone changes nothing the server would see.
watch(() => searchQuery.value.trim(), () => queueFetch(500));

// includeMapped rides the same reload as the filters, so toggling it and clearing the
// filters in the same tick still makes one request, not two.
watch([filters, includeMapped], () => {
    // The server would refuse it; the field says why instead of the list going quiet.
    if (membersRangeInvalid.value) return;
    // Per-entry counts are taken under the other filters, so they move with them.
    markMatchesStale();
    queueFetch(400);
}, { deep: true });

// Keep the tab on the view the server settled on.
watch(() => pageProps.value.scope, (next) => {
    if (isView(next)) scope.value = next;
});

/**
 * Opening a row.
 *
 * Every row says what it is, which is what decides the lookup — an account row opened as
 * a branch would look up the wrong code. Only a row that somehow does not say falls back
 * to the view on screen (never the tab just clicked: the row belongs to what is rendered).
 */
const openRow = (row: DirectoryRow) =>
    openDirectoryRow(row, row.kind ?? (renderedScope.value as DirectoryScope));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Unmapped Accounts" />

        <div class="flex flex-1 flex-col gap-4 p-4">
            <Card class="gap-0 py-0">
                <!-- Title and live result count -->
                <CardHeader class="flex flex-col gap-1.5 border-b px-4 py-5 sm:px-6 [.border-b]:pb-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-lg leading-none font-semibold tracking-tight">Unmapped Accounts</h1>
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground"
                            aria-live="polite">
                            {{ resultSummary }}
                        </span>
                    </div>
                    <CardDescription>
                        Accounts and branches in the HMS directory that no user has been given access to yet.
                        Search names or codes across both at once, or many at a time with bulk search.
                        Account classes that are never mapped to a user are left out.
                    </CardDescription>
                </CardHeader>

                <!--
                    Accounts / Branches. The tabs share the toolbar with the search they
                    scope; each tab's panel is the listing itself.
                -->
                <Tabs :model-value="scope" @update:model-value="switchScope">
                    <!-- View, search and filters -->
                    <CardContent class="flex flex-col gap-3 border-b px-4 py-4 sm:px-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <TabsList class="h-9 self-start">
                                <TabsTrigger
                                    v-for="option in scopes"
                                    :key="String(option.value)"
                                    :value="String(option.value)"
                                    class="cursor-pointer">
                                    {{ option.name }}
                                </TabsTrigger>
                            </TabsList>
                            <ListSearch
                                v-if="!bulkSearchOpen"
                                id="unmapped-search"
                                v-model="searchQuery"
                                label="Search directory"
                                :placeholder="viewCopy.placeholder" />
                            <!-- Many names or codes at once: opens in place of the single search -->
                            <Button
                                type="button"
                                class="cursor-pointer self-start sm:self-auto"
                                :variant="bulkSearchOpen ? 'default' : 'outline'"
                                :aria-expanded="bulkSearchOpen"
                                aria-controls="bulk-directory-search"
                                @click="toggleBulkSearch">
                                <TextSearch /> Bulk search
                            </Button>
                        </div>

                        <BulkSearch
                            v-if="bulkSearchOpen"
                            id="bulk-directory-search"
                            title="Search multiple accounts or branches"
                            description="Enter account or branch names or codes separated by commas, semicolons, or new lines."
                            placeholder="AT-12681304, BR1907150430; SM City Valenzuela"
                            :noun="{ one: 'account or branch', many: 'accounts and branches' }"
                            :matches="searchTermMatches"
                            :applied="bulkLookup !== null"
                            :max-terms="maxSearchTerms"
                            :focus="bulkLookup?.focus ?? null"
                            @update:focus="focusBulkTerm"
                            @search="runBulkLookup"
                            @clear="clearBulkLookup"
                            @close="toggleBulkSearch" />

                        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filters">
                            <span class="flex items-center gap-1.5 pr-1 text-xs font-medium text-muted-foreground">
                                <SlidersHorizontal class="size-3.5" aria-hidden="true" /> Filters
                            </span>

                            <FilterSelect
                                v-for="def in filterDefs"
                                :key="def.key"
                                v-model="filters[def.key]"
                                :label="def.label"
                                :all-label="def.allLabel"
                                :options="def.options"
                                :class="def.width" />

                            <!-- Members held by the account or branch, as one bordered range like the date range elsewhere -->
                            <div
                                :class="membersControlClass"
                                role="group"
                                aria-label="Members"
                                :aria-invalid="membersRangeInvalid || undefined"
                                :aria-describedby="membersRangeInvalid ? 'members-range-error' : undefined">
                                <span class="pr-2 text-muted-foreground select-none">Members</span>
                                <input
                                    id="members-min"
                                    v-model="filters.members_min"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="0"
                                    aria-label="Members from"
                                    class="w-14 bg-transparent tabular-nums outline-none" />
                                <span class="px-1 text-muted-foreground select-none" aria-hidden="true">–</span>
                                <input
                                    id="members-max"
                                    v-model="filters.members_max"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="any"
                                    aria-label="Members to"
                                    class="w-14 bg-transparent tabular-nums outline-none" />
                            </div>
                            <span
                                v-if="membersRangeInvalid"
                                id="members-range-error"
                                class="text-xs text-red-600 dark:text-red-400"
                                role="alert">
                                The maximum can't be lower than the minimum
                            </span>

                            <!--
                                Off by default, so the listing stays the coverage gap it's
                                named for. Switched on, the search also matches what is
                                already mapped, and the Mapping column says who has it.
                            -->
                            <label class="flex h-9 cursor-pointer items-center gap-2 px-1 text-sm text-muted-foreground select-none">
                                <Switch v-model="includeMapped" />
                                Include mapped {{ rowNoun.many }}
                            </label>

                            <Button
                                v-if="activeFilterCount"
                                variant="ghost"
                                size="sm"
                                class="h-9 cursor-pointer px-2 text-muted-foreground hover:text-foreground"
                                @click="clearFilters">
                                <X class="size-3.5" /> Clear filters ({{ activeFilterCount }})
                            </Button>
                        </div>
                    </CardContent>

                    <!--
                        One panel per view, rather than a single panel told which view it
                        is: a panel keeps the id it registered with, so a changing `value`
                        leaves the selected tab pointing at an id that is no longer there.
                        Only the selected one is mounted, so the listing is still built once.
                    -->
                    <TabsContent
                        v-for="option in scopes"
                        :key="String(option.value)"
                        :value="String(option.value)"
                        class="mt-0">
                        <!-- The listing; dimmed while a reload is in flight -->
                        <CardContent
                            class="px-4 py-4 transition-opacity duration-200 sm:px-6"
                            :class="{ 'opacity-60': isFetching }"
                            :aria-busy="isFetching">
                            <Datatable
                                :data="directory.data"
                                :columns="columns"
                                :pagination="pagination"
                                :enable-search="false"
                                :enable-row-click="true"
                                :row-click="openRow"
                                :empty-message="emptyState.message"
                                :empty-description="emptyState.description"
                                :export-file-name="exportFileName"
                                @update:pagination="onPaginationChange" />
                        </CardContent>
                    </TabsContent>
                </Tabs>
            </Card>
        </div>

        <RightPane
            :open="rightPaneVisible"
            :title="rightPaneTitle"
            :loading="rightPaneLoading"
            :error="rightPaneError"
            :content-component="rightPaneContentComponent"
            :component-props="rightPaneComponentProps"
            @update:open="(v) => { if (!v && !rightPaneLoading) closePane('right') }" />
    </AppLayout>
</template>
