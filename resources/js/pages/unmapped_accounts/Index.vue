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
 * Accounts and branches are two views of one listing rather than two pages: the
 * filters mean the same thing in both, so switching tabs keeps them. Only the tab
 * being looked at is fetched — the directory is tens of thousands of rows on a remote
 * database, and answering the other one would double the cost of every page.
 */
import { ref, watch, computed, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from '@/components/ui/button';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectItem, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useModulePermissions } from '@/composables/useModulePermissions';
import RightPane from '@/components/RightPane.vue';
import { useUnmappedAccounts, type DirectoryRow, type DirectoryScope } from '@/composables/unmappedAccounts';
import { badge, mappedStatusBadge } from '@/lib/directoryBadges';
import { SlidersHorizontal, X } from 'lucide-vue-next';

type DirectoryPagination = {
    current_page: number
    per_page: number
    total: number
    data: DirectoryRow[]
}

type Option = { value: string | number; name: string }

const SCOPE_BRANCH = 'branch';

const page = usePage();
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

const directory = computed<DirectoryPagination>(() => {
    const props = (page.props as any).directory as DirectoryPagination | undefined;

    return props ?? { current_page: 1, per_page: 10, total: 0, data: [] };
});

const filterOptions = computed(() => {
    const opts = (page.props as any).filter_options as
        | { scopes?: Option[]; code_prefixes?: Option[]; account_types?: Option[]; statuses?: Option[] }
        | undefined;

    return {
        scopes: opts?.scopes ?? [],
        codePrefixes: opts?.code_prefixes ?? [],
        accountTypes: opts?.account_types ?? [],
        statuses: opts?.statuses ?? [],
    };
});

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
const scope = ref<string>(((page.props as any).scope as string) ?? 'account');
const renderedScope = computed<string>(() => ((page.props as any).scope as string) ?? scope.value);
const isBranchScope = computed(() => renderedScope.value === SCOPE_BRANCH);

const columnHelper = createColumnHelper();
const pagination = ref({
    current_page: directory.value.current_page,
    per_page: Number(directory.value.per_page),
    total: directory.value.total,
});

const searchQuery = ref('');
const hasInitialized = ref(false);

// --- Filters ---
const FILTER_ALL = 'all'; // sentinel — means "no filter applied"

const filters = ref({ code_prefix: '', account_type: '', is_active: '', members_min: '', members_max: '' });

/**
 * Off by default, so the listing stays the coverage gap it's named for: only what
 * nobody has. Switched on, the search widens to also match what is already mapped —
 * from `user_accounts` — so a code that looks missing can be confirmed as taken rather
 * than left ambiguous.
 */
const includeMapped = ref(false);

const filtersActive = computed(() => Object.values(filters.value).some((value) => value !== '') || includeMapped.value);

/** Selects bind to a sentinel rather than '' so "All" is a real, selectable option. */
const asSelectModel = (key: 'code_prefix' | 'account_type' | 'is_active') => computed({
    get: () => filters.value[key] || FILTER_ALL,
    set: (v: string | undefined) => { filters.value[key] = v === FILTER_ALL ? '' : (v ?? ''); },
});

const codePrefixModel = asSelectModel('code_prefix');
const accountTypeModel = asSelectModel('account_type');
const statusModel = asSelectModel('is_active');

const clearFilters = () => {
    filters.value = { code_prefix: '', account_type: '', is_active: '', members_min: '', members_max: '' };
    includeMapped.value = false;
};

const TYPE_CLASSES: Record<string, string> = {
    T: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
    H: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
};

/** Right-aligned and grouped: these run to five figures and are read as magnitudes. */
const memberCell = (value: unknown) =>
    h('div', { class: 'text-right tabular-nums' }, Number(value ?? 0).toLocaleString());

const codeCell = (value: unknown) =>
    h('span', { class: 'font-mono text-xs' }, String(value ?? '—'));

/** Appended only while `includeMapped` is on — otherwise every row would read "Unmapped". */
const mappedColumn = columnHelper.accessor('mapped_users', {
    header: 'Mapping',
    cell: (info: any) => mappedStatusBadge(info.getValue()),
});

const accountColumns: any[] = [
    columnHelper.accessor('account_name', {
        header: 'Account',
        cell: (info: any) => info.getValue() || '—',
    }),
    columnHelper.accessor('account_code', {
        header: 'Account Code',
        cell: (info: any) => codeCell(info.getValue()),
    }),
    columnHelper.accessor('code_prefix', {
        header: 'Prefix',
        cell: (info: any) => info.getValue()
            ? badge(info.getValue(), 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300')
            : '—',
    }),
    columnHelper.accessor('account_type_label', {
        header: 'Type',
        cell: (info: any) => badge(
            info.getValue() ?? '—',
            TYPE_CLASSES[info.row.original?.account_type ?? ''] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        ),
    }),
    columnHelper.accessor('is_active', {
        header: 'Status',
        cell: (info: any) => info.getValue()
            ? badge('Active', 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400')
            : badge('Inactive', 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'),
    }),
    columnHelper.accessor('member_count', {
        header: 'Members',
        cell: (info: any) => memberCell(info.getValue()),
    }),
];

const branchColumns: any[] = [
    columnHelper.accessor('branch_name', {
        header: 'Branch',
        cell: (info: any) => info.getValue() || '—',
    }),
    columnHelper.accessor('branch_code', {
        header: 'Branch Code',
        cell: (info: any) => codeCell(info.getValue()),
    }),
    columnHelper.accessor('account_name', {
        header: 'Account',
        cell: (info: any) => info.getValue() || '—',
    }),
    columnHelper.accessor('account_code', {
        header: 'Account Code',
        cell: (info: any) => codeCell(info.getValue()),
    }),
    columnHelper.accessor('account_type_label', {
        header: 'Type',
        cell: (info: any) => badge(
            info.getValue() ?? '—',
            TYPE_CLASSES[info.row.original?.account_type ?? ''] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        ),
    }),
    columnHelper.accessor('member_count', {
        header: 'Members',
        cell: (info: any) => memberCell(info.getValue()),
    }),
];

const columns = computed(() => {
    const base = isBranchScope.value ? branchColumns : accountColumns;

    return includeMapped.value ? [...base, mappedColumn] : base;
});

const searchPlaceholder = computed(() => isBranchScope.value
    ? 'Search branch, account or code...'
    : 'Search account name or code...');

const emptyMessage = computed(() => {
    if (includeMapped.value) return isBranchScope.value ? 'No branches found' : 'No accounts found';

    return isBranchScope.value ? 'No unmapped branches found' : 'No unmapped accounts found';
});

const emptyDescription = computed(() => {
    if (includeMapped.value) return 'Nothing in the HMS directory matches these filters.';

    return isBranchScope.value
        ? 'Every branch matching these filters is already assigned to a user.'
        : 'Every account matching these filters is already assigned to a user.';
});

const exportFileName = computed(() => (isBranchScope.value ? 'unmapped_branches' : 'unmapped_accounts'));

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Unmapped Accounts',
        href: slug.value,
    },
];

const fetchDirectory = () => {
    const params: Record<string, any> = {
        scope: scope.value,
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
    };

    if (searchQuery.value.trim()) params.search_string = searchQuery.value.trim();
    if (filters.value.code_prefix) params.code_prefix = filters.value.code_prefix;
    if (filters.value.account_type) params.account_type = filters.value.account_type;
    // '' means "either"; '0' is a real choice, so this cannot be a falsy test.
    if (filters.value.is_active !== '') params.is_active = filters.value.is_active;
    // '' means "no bound"; 0 is a real bound, so the emptiness test cannot be falsy.
    if (filters.value.members_min !== '') params.members_min = filters.value.members_min;
    if (filters.value.members_max !== '') params.members_max = filters.value.members_max;
    if (includeMapped.value) params.include_mapped = 1;

    router.get(`/${slug.value}`, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['directory', 'scope'],
    });
};

/** Switching view starts the listing over: page 3 of accounts is not page 3 of branches. */
const switchScope = (next: string | number | undefined) => {
    const value = String(next ?? '');

    if (!value || value === scope.value) return;

    scope.value = value;
    pagination.value.current_page = 1;
    hasInitialized.value = true;
    fetchDirectory();
};

// Debounced fetch for the search box
const searchTimeout = ref<number | null>(null);
watch(searchQuery, () => {
    if (!hasInitialized.value) return;
    if (searchTimeout.value) clearTimeout(searchTimeout.value);

    searchTimeout.value = window.setTimeout(() => {
        pagination.value.current_page = 1;
        fetchDirectory();
    }, 500);
});

// Keep local pagination — and the scope the server settled on — in step with the response
const isUpdatingFromServer = ref(false);
watch(directory, (next) => {
    if (!next) return;
    isUpdatingFromServer.value = true;
    pagination.value.current_page = next.current_page;
    pagination.value.per_page = Number(next.per_page);
    pagination.value.total = next.total;

    setTimeout(() => { isUpdatingFromServer.value = false; }, 300);
});

watch(() => (page.props as any).scope, (next) => {
    if (next) scope.value = String(next);
});

// Debounced fetch for paging
const fetchTimeout = ref<number | null>(null);
watch(
    () => [pagination.value.current_page, pagination.value.per_page],
    ([currentPage, perPage]) => {
        if (!hasInitialized.value || isUpdatingFromServer.value) return;
        if (fetchTimeout.value) clearTimeout(fetchTimeout.value);

        fetchTimeout.value = window.setTimeout(() => {
            pagination.value.current_page = Number(currentPage) || 1;
            pagination.value.per_page = Number(perPage) || 10;
            fetchDirectory();
        }, 50);
    },
);

// Debounced fetch when any filter changes — includeMapped rides the same channel so
// toggling it and clearing filters in the same tick still fires one request, not two.
const filterTimeout = ref<number | null>(null);
watch([filters, includeMapped], () => {
    if (filterTimeout.value) clearTimeout(filterTimeout.value);

    filterTimeout.value = window.setTimeout(() => {
        pagination.value.current_page = 1;
        hasInitialized.value = true;
        fetchDirectory();
    }, 400);
}, { deep: true });

/**
 * Opening a row.
 *
 * The scope comes from what is rendered rather than from the tab just clicked: the row
 * belongs to the listing on screen, and an account row opened as a branch would look up
 * the wrong code.
 */
const openRow = (row: DirectoryRow) => openDirectoryRow(row, renderedScope.value as DirectoryScope);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Unmapped Accounts" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <!-- What this is + search -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                <p class="text-sm text-[var(--color-text-muted)]">
                    Accounts and branches in the HMS directory that no user has been given access to yet.
                    Account classes that are never mapped to a user are left out.
                </p>
                <div class="relative w-full sm:w-72">
                    <label class="sr-only" for="unmapped-search">Search directory</label>
                    <input
                        id="unmapped-search"
                        v-model="searchQuery"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="border border-[var(--color-border-strong)] rounded-md text-sm bg-[var(--color-surface)] text-[var(--color-text)] focus:ring-2 focus:ring-opacity-50 focus:border-transparent w-full px-4 py-2 pr-8"
                        :style="{ '--tw-ring-color': 'var(--primary-color)' }"
                        @input="hasInitialized = true" />
                    <button
                        v-if="searchQuery"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none"
                        aria-label="Clear search"
                        @click="searchQuery = ''">
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!--
                Accounts / Branches. The listing is the tab's own panel rather than a
                sibling of it, so the two are announced as one thing; the filters
                narrow that listing, so they sit inside it too.
            -->
            <Tabs :model-value="scope" @update:model-value="switchScope">
                <TabsList>
                    <TabsTrigger
                        v-for="option in filterOptions.scopes"
                        :key="String(option.value)"
                        :value="String(option.value)">
                        {{ option.name }}
                    </TabsTrigger>
                </TabsList>

                <!--
                    One panel per view, rather than a single panel told which view it
                    is: a panel keeps the id it registered with, so a changing `value`
                    leaves the selected tab pointing at an id that is no longer there.
                    Only the selected one is mounted, so the listing is still built once.
                -->
                <TabsContent
                    v-for="option in filterOptions.scopes"
                    :key="String(option.value)"
                    :value="String(option.value)"
                    class="mt-3 flex flex-col gap-3">
                    <!-- Filter row -->
                    <div class="flex flex-wrap items-end gap-2">
                        <SlidersHorizontal class="mb-2 w-4 h-4 shrink-0 text-[var(--color-text-muted)]" aria-hidden="true" />

                        <!-- Account code prefix -->
                        <Select v-model="codePrefixModel">
                            <SelectTrigger class="h-8 w-40 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                        All code prefixes
                                    </SelectItem>
                                    <SelectItem
                                        v-for="opt in filterOptions.codePrefixes"
                                        :key="String(opt.value)"
                                        :value="String(opt.value)"
                                        class="text-xs">
                                        {{ opt.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <!-- Account type -->
                        <Select v-model="accountTypeModel">
                            <SelectTrigger class="h-8 w-36 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                        All account types
                                    </SelectItem>
                                    <SelectItem
                                        v-for="opt in filterOptions.accountTypes"
                                        :key="String(opt.value)"
                                        :value="String(opt.value)"
                                        class="text-xs">
                                        {{ opt.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <!--
                            Status. A branch has none of its own, so this asks about
                            the account it belongs to — the same account the type and
                            prefix filters already classify it by.
                        -->
                        <Select v-model="statusModel">
                            <SelectTrigger class="h-8 w-36 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                        Any status
                                    </SelectItem>
                                    <SelectItem
                                        v-for="opt in filterOptions.statuses"
                                        :key="String(opt.value)"
                                        :value="String(opt.value)"
                                        class="text-xs">
                                        {{ opt.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <!--
                            Off by default, so the listing stays the coverage gap it's
                            named for. Switched on, the search also matches what is
                            already mapped, and the Mapping column says who has it.
                        -->
                        <label class="flex h-8 items-center gap-1.5 px-1 text-xs text-[var(--color-text-muted)] cursor-pointer select-none">
                            <input
                                v-model="includeMapped"
                                type="checkbox"
                                class="h-3.5 w-3.5 rounded border-[var(--color-border-strong)]"
                                :style="{ accentColor: 'var(--primary-color)' }" />
                            Include mapped {{ isBranchScope ? 'branches' : 'accounts' }}
                        </label>

                        <!-- Members held by the account or branch -->
                        <div class="flex items-end gap-1">
                            <div class="flex flex-col">
                                <label class="mb-0.5 text-[10px] uppercase tracking-wide text-[var(--color-text-muted)]" for="members-min">
                                    Members from
                                </label>
                                <input
                                    id="members-min"
                                    v-model="filters.members_min"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="0"
                                    class="h-8 w-24 border border-[var(--color-border-strong)] rounded-md text-xs bg-[var(--color-surface)] text-[var(--color-text)] px-2 focus:ring-2 focus:ring-opacity-50 focus:border-transparent"
                                    :style="{ '--tw-ring-color': 'var(--primary-color)' }" />
                            </div>
                            <div class="flex flex-col">
                                <label class="mb-0.5 text-[10px] uppercase tracking-wide text-[var(--color-text-muted)]" for="members-to">
                                    to
                                </label>
                                <input
                                    id="members-to"
                                    v-model="filters.members_max"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="any"
                                    class="h-8 w-24 border border-[var(--color-border-strong)] rounded-md text-xs bg-[var(--color-surface)] text-[var(--color-text)] px-2 focus:ring-2 focus:ring-opacity-50 focus:border-transparent"
                                    :style="{ '--tw-ring-color': 'var(--primary-color)' }" />
                            </div>
                        </div>

                        <Button
                            v-if="filtersActive"
                            variant="ghost"
                            size="sm"
                            class="mb-0.5 h-8 px-2 text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
                            @click="clearFilters">
                            <X class="w-3 h-3 mr-1" />
                            Clear
                        </Button>
                    </div>

                    <Datatable
                        :data="directory.data"
                        :columns="columns"
                        :pagination="pagination"
                        :enable-search="false"
                        :enable-row-click="true"
                        :row-click="openRow"
                        :empty-message="emptyMessage"
                        :empty-description="emptyDescription"
                        :export-file-name="exportFileName"
                        @update:pagination="(newPagination: typeof pagination) => { hasInitialized = true; pagination = newPagination }" />
                </TabsContent>
            </Tabs>
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
