<script setup lang="ts">
/**
 * The users directory: find users by search, bulk lookup or filter, act on one from its
 * row or on many from a selection, and export credential reports over what is listed.
 *
 * The first page arrives with the page itself; every change after that — typing, a
 * filter, a page click, a bulk lookup — goes through one debounced reload (`queueFetch`),
 * so changes made in quick succession make one request rather than one each.
 */
import { computed, h, ref, watch, type Component } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { createColumnHelper, type ColumnDef } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import ListSearch from '@/components/ListSearch.vue';
import RightPane from '@/components/RightPane.vue';
import TopPane from '@/components/TopPane.vue';
import { Button, type ButtonVariants } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import BulkUserSearch from '@/components/forms/users/BulkUserSearch.vue';
import { createRowActionsColumn, type RowAction } from '@/composables/datatable/rowActions';
import { useServerListing, type ListingPage } from '@/composables/datatable/useServerListing';
import { useModulePermissions } from '@/composables/useModulePermissions';
import {
    useUsers,
    type BulkUserLookup,
    type CredentialReportOption,
    type SearchTermMatch,
    type User,
    type UserCredentials,
} from '@/composables/users';
import { avatar } from '@/lib/avatar';
import { badge } from '@/lib/directoryBadges';
import { timeAgo } from '@/lib/relativeTime';
import {
    ChevronDown,
    FileDown,
    KeyRound,
    MailCheck,
    RotateCcw,
    SlidersHorizontal,
    TextSearch,
    ToggleLeft,
    ToggleRight,
    Trash2,
    Upload,
    UserPlus,
    UserRoundCog,
    X,
} from 'lucide-vue-next';

/** One list row, as `UserListResource` shapes it. */
interface UserRow extends User {
    id: number
    username: string
    email: string
    is_active: number | boolean
    created_at: string | null
    /** The sortable timestamp behind the `created_at` label. */
    created_at_value: string | null
    type: number | null
    type_label: string | null
    /** Whether the type is mapped to accounts and branches (`UserType::allowsAccountMapping`). */
    allows_account_mapping: boolean
    department_id: number | null
    department: string | null
    credentials?: UserCredentials
}

type UsersPagination = ListingPage & { data: UserRow[] }

type Option = { value: number | string; name: string }

type FilterOptions = {
    user_types: Option[]
    departments: { id: number; name: string }[]
    credential_statuses: Option[]
    credential_accesses: Option[]
}

/** A row action as the shared `sub_modules` prop lists it (a navigation module). */
type SubModule = { slug: string; name?: string; icon?: string }

/** What `UserController::index` sends, plus the shared `sub_modules`. */
interface UsersPageProps {
    users?: UsersPagination
    search_term_matches?: SearchTermMatch[]
    max_search_terms?: number
    filter_options?: Partial<FilterOptions>
    credential_reports?: CredentialReportOption[]
    sub_modules?: SubModule[]
}

const page = usePage();
const pageProps = computed(() => page.props as unknown as UsersPageProps);
const { slug, hasPermission, canCreate } = useModulePermissions();
const {
    createUser,
    bulkImportUsers,
    editUser,
    deleteUser,
    manageUserRoles,
    bulkManageUserRoles,
    manageUserPermissions,
    bulkManageUserPermissions,
    exportCredentialReport,
    bulkToggleActiveUsers,
    bulkDeleteUsers,
    verifyUsers,
    bulkVerifyCredentials,
    toggleActiveUser,
    openUserPane,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
    topPaneVisible,
    topPaneTitle,
    topPaneLoading,
    topPaneError,
    topPaneContentComponent,
    topPaneComponentProps,
} = useUsers();

const EMPTY_PAGE: UsersPagination = { current_page: 1, per_page: 10, total: 0, data: [] };

const users = computed(() => pageProps.value.users ?? EMPTY_PAGE);

const searchQuery = ref('');

/** The reports the export menu offers — the server's list (`UserCredentialReport`). */
const credentialReports = computed(() => pageProps.value.credential_reports ?? []);
const canExport = computed(() => hasPermission(`${slug.value}.export`) && credentialReports.value.length > 0);

// --- Bulk lookup ("Search multiple users") ---
/** The applied lookup, plus the one entry the list is narrowed to (null: every entry's matches). */
const bulkLookup = ref<(BulkUserLookup & { focus: string | null }) | null>(null);
const bulkSearchOpen = ref(false);
/**
 * Whether the per-entry counts must be reloaded with the list. They depend on the entries
 * and the other filters, not on paging or on which entry is singled out — so those
 * reloads leave the server's lazy `search_term_matches` prop, and its aggregate, out.
 */
const matchesStale = ref(false);

const searchTermMatches = computed(() => pageProps.value.search_term_matches ?? []);
const maxSearchTerms = computed(() => Number(pageProps.value.max_search_terms) || 100);

// --- Filters ---
const VC_EMPLOYEE_TYPE = '1'; // UserType::VC_EMPLOYEE

type FilterKey = 'type' | 'department_id' | 'status' | 'credential_status' | 'credential_access'

/** One filter dropdown: what it narrows, how "no filter" reads, and the choices it offers. */
interface FilterDef {
    key: FilterKey
    label: string
    allLabel: string
    options: { value: string; label: string }[]
    width: string
}

const emptyFilters = (): Record<FilterKey, string> =>
    ({ type: '', department_id: '', status: '', credential_status: '', credential_access: '' });
const filters = ref(emptyFilters());

const activeFilterCount = computed(() => Object.values(filters.value).filter((value) => value !== '').length);

const STATUS_OPTIONS = [
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' },
];

const toSelectOptions = (options: Option[] = []) => options.map(({ value, name }) => ({ value: String(value), label: name }));

/**
 * The filter bar, in order. Departments belong to VC employees only, so that filter
 * joins the bar once the type is chosen rather than sitting there disabled.
 */
const filterDefs = computed<FilterDef[]>(() => {
    const options = pageProps.value.filter_options;
    const defs: FilterDef[] = [
        { key: 'type', label: 'User type', allLabel: 'All types', options: toSelectOptions(options?.user_types), width: 'w-44' },
        {
            key: 'department_id',
            label: 'Department',
            allLabel: 'All departments',
            options: (options?.departments ?? []).map(({ id, name }) => ({ value: String(id), label: name })),
            width: 'w-52',
        },
        { key: 'status', label: 'Status', allLabel: 'All statuses', options: STATUS_OPTIONS, width: 'w-36' },
        { key: 'credential_status', label: 'Password status', allLabel: 'Any password status', options: toSelectOptions(options?.credential_statuses), width: 'w-52' },
        { key: 'credential_access', label: 'Credential access', allLabel: 'Any credential access', options: toSelectOptions(options?.credential_accesses), width: 'w-52' },
    ];

    return defs.filter((def) => def.key !== 'department_id' || filters.value.type === VC_EMPLOYEE_TYPE);
});

// Departments belong to VC employees only, so leaving that type drops the department with it.
watch(() => filters.value.type, (type) => {
    if (type !== VC_EMPLOYEE_TYPE) filters.value.department_id = '';
});

const clearFilters = () => {
    filters.value = emptyFilters();
};

// --- Summary & empty state ---
/** Whether anything narrows the list — which turns "users" into "matching users". */
const isNarrowed = computed(() => searchQuery.value.trim() !== '' || bulkLookup.value !== null || activeFilterCount.value > 0);

const resultSummary = computed(() => {
    const total = users.value.total;

    return `${total.toLocaleString()} ${isNarrowed.value ? 'matching ' : ''}${total === 1 ? 'user' : 'users'}`;
});

const emptyState = computed(() => {
    if (bulkLookup.value) {
        return {
            message: 'No users match these entries',
            description: bulkLookup.value.exact
                ? 'Check the entries for typos, or turn off exact match.'
                : 'Check the entries for typos.',
        };
    }

    if (isNarrowed.value) {
        return { message: 'No users match your search', description: 'Try a different search term or clear the filters.' };
    }

    return { message: 'No users yet', description: 'Users you create or import will appear here.' };
});

// --- Columns ---
const DASH = '—';

const DELETED_BADGE = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';

/** `App\Enums\CredentialStatus` — the states the list reacts to. */
const CREDENTIAL_STATUS = { NOT_SENT: 0, EXPIRED: 2 } as const;

const isActive = (user: UserRow) => Number(user.is_active) !== 0;
const isDeleted = (user: UserRow) => Boolean(user.deleted_at);

/** Never sent, or the temporary password lapsed unused: (re)sending them is what the user is waiting on. */
const awaitsCredentials = (user: UserRow) =>
    user.credentials?.status === CREDENTIAL_STATUS.NOT_SENT || user.credentials?.status === CREDENTIAL_STATUS.EXPIRED;

const canVerify = computed(() => hasPermission(`${slug.value}.verify`));

/** Who the user is at a glance — avatar, username, id and email — flagged when soft-deleted. */
const userCell = (user: UserRow) => h('div', { class: 'flex items-center gap-2.5' }, [
    avatar(user.username),
    h('div', { class: 'min-w-0 max-w-72' }, [
        h('div', { class: 'flex items-center gap-1.5' }, [
            h('span', { class: 'truncate font-medium' }, user.username),
            h('span', { class: 'shrink-0 tabular-nums text-muted-foreground' }, `#${user.id}`),
            isDeleted(user) ? badge('Deleted', DELETED_BADGE) : null,
        ]),
        h('div', { class: 'truncate text-muted-foreground' }, user.email || DASH),
    ]),
]);

/** A value with a muted detail beneath it, so related facts share one column. */
const stackedCell = (primary: string, secondary?: string | null) => h('div', { class: 'min-w-0' }, [
    h('div', { class: 'truncate' }, primary),
    secondary ? h('div', { class: 'truncate text-muted-foreground' }, secondary) : null,
]);

/** A coloured dot and a word — status is binary, so it needs less ink than the credential pill beside it. */
const statusCell = (user: UserRow) => {
    const active = isActive(user);

    return h('span', { class: 'inline-flex items-center gap-1.5 whitespace-nowrap' }, [
        h('span', { class: ['size-2 rounded-full', active ? 'bg-green-500' : 'bg-orange-500'], 'aria-hidden': 'true' }),
        active ? 'Active' : 'Inactive',
    ]);
};

/**
 * The credential status as the server labels and colours it (CredentialStatus), with the
 * dates behind it in a tooltip. Beneath it, what to do next: send the credentials when the
 * user is still waiting on them, otherwise when they last signed in.
 */
const credentialsCell = (user: UserRow) => {
    const credentials = user.credentials;
    if (!credentials) return DASH;

    const dates = ([
        ['Sent', credentials.sent_at],
        ['Temporary expires', credentials.temporary_expires_at],
        ['Password updated', credentials.password_changed_at],
        ['Last login', credentials.last_login_at ?? 'Never'],
    ] as [string, string | null][]).filter((entry): entry is [string, string] => Boolean(entry[1]));

    const status = h(Tooltip, null, {
        default: () => [
            h(TooltipTrigger, { asChild: true }, () =>
                h('span', { class: 'inline-flex', tabindex: 0 }, [badge(credentials.status_label, credentials.status_color)]),
            ),
            h(TooltipContent, { align: 'start' }, () =>
                h('dl', { class: 'grid grid-cols-[auto_auto] gap-x-3 gap-y-0.5' }, dates.flatMap(([term, value]) => [
                    h('dt', { class: 'opacity-70' }, term),
                    h('dd', null, value),
                ])),
            ),
        ],
    });

    const next = canVerify.value && awaitsCredentials(user)
        ? h(
            'button',
            {
                type: 'button',
                class: 'cursor-pointer font-medium whitespace-nowrap text-blue-600 hover:underline dark:text-blue-400',
                onClick: () => verifyUsers([user]),
            },
            credentials.status === CREDENTIAL_STATUS.EXPIRED ? 'Resend credentials' : 'Send credentials',
        )
        : h(
            'span',
            { class: 'whitespace-nowrap text-muted-foreground' },
            credentials.last_login_at_value ? `Last login ${timeAgo(credentials.last_login_at_value)}` : 'Never logged in',
        );

    return h('div', { class: 'flex flex-col items-start gap-0.5' }, [status, next]);
};

/**
 * What each sub-module action (`users.<action>`) does on a row, in menu order. `resolve`
 * fits the sub-module's own name and icon to the row, or returns null where the action
 * does not apply; sub-modules not listed here are not offered on rows.
 */
const rowActionSpecs: Record<string, {
    run: (user: UserRow) => unknown
    resolve?: (user: UserRow) => Partial<Omit<RowAction, 'key' | 'onSelect'>> | null
}> = {
    // What a row is most often opened for, so it stays out of the menu.
    edit: { run: editUser, resolve: () => ({ inline: true }) },
    account_mapping: {
        // The action opens the same pane a row click does, straight onto the mapping tab.
        run: (user) => openUserPane(user, 'account_mapping'),
        // Only the account-scoped types are mapped to accounts and branches; the
        // server decides which those are (UserType::allowsAccountMapping).
        resolve: (user) => (user.allows_account_mapping ? {} : null),
    },
    edit_roles: { run: manageUserRoles },
    edit_permissions: { run: manageUserPermissions },
    verify: { run: (user) => verifyUsers([user]) },
    toggle_active: {
        run: toggleActiveUser,
        resolve: (user) => isActive(user)
            ? { label: 'Deactivate User', icon: ToggleRight }
            : { label: 'Activate User', icon: ToggleLeft },
    },
    destroy: {
        run: deleteUser,
        resolve: (user) => isDeleted(user)
            ? { label: 'Restore User', icon: RotateCcw }
            : { label: 'Delete User', icon: Trash2, destructive: true },
    },
};

/** The row actions this user may take: permitted sub-modules this page knows how to run, in menu order. */
const permittedRowActions = computed(() => {
    const modules = new Map((pageProps.value.sub_modules ?? []).map((module) => [module.slug.split('.')[1], module]));

    return Object.entries(rowActionSpecs).flatMap(([action, spec]) => {
        const module = modules.get(action);

        return module && hasPermission(module.slug) ? [{ action, module, spec }] : [];
    });
});

const rowActionsFor = (user: UserRow): RowAction[] => permittedRowActions.value.flatMap(({ action, module, spec }) => {
    const resolved = spec.resolve ? spec.resolve(user) : {};
    if (resolved === null) return [];

    return [{ key: action, label: module.name ?? action, icon: module.icon, ...resolved, onSelect: () => spec.run(user) }];
});

const columnHelper = createColumnHelper<UserRow>();

// Cells read permissions and sub-modules as they render, so the column list itself is fixed.
const columns: ColumnDef<UserRow, any>[] = [
    columnHelper.accessor('username', {
        header: 'User',
        cell: ({ row }) => userCell(row.original),
    }),
    columnHelper.accessor('type_label', {
        header: 'Type',
        // The department only applies to VC employees, so it rides under the type.
        cell: ({ row }) => stackedCell(row.original.type_label ?? DASH, row.original.department),
    }),
    columnHelper.accessor('is_active', {
        header: 'Status',
        cell: ({ row }) => statusCell(row.original),
    }),
    columnHelper.accessor((row) => row.credentials?.status_label ?? DASH, {
        id: 'credentials',
        header: 'Credentials',
        cell: ({ row }) => credentialsCell(row.original),
    }),
    columnHelper.accessor('created_at', {
        header: 'Created',
        // The server sends the label; `created_at_value` carries the sortable timestamp.
        sortingFn: (a, b) =>
            String(a.original.created_at_value ?? '').localeCompare(String(b.original.created_at_value ?? '')),
        cell: (info) => h('span', { class: 'whitespace-nowrap' }, info.getValue() ?? DASH),
    }),
    createRowActionsColumn<UserRow>(rowActionsFor, (user) => `Actions for ${user.username}`),
];

/** A row click opens the pane on the details tab; the mapping action opens it on the other. */
const openUserDetails = (user: UserRow) => openUserPane(user, 'details');

// --- Bulk actions ---
/** One action on the selection: its permission (`users.<permission>`) and the selected users it applies to. */
interface BulkAction {
    key: string
    permission: string
    label: string
    icon: Component
    variant?: ButtonVariants['variant']
    class?: string
    /** Which selected users it acts on; omitted, all of them. Left out when it applies to none. */
    appliesTo?: (user: UserRow) => boolean
    run: (users: UserRow[]) => unknown
}

const bulkActions: BulkAction[] = [
    { key: 'roles', permission: 'edit_roles', label: 'Manage Roles', icon: UserRoundCog, variant: 'outline', run: bulkManageUserRoles },
    { key: 'permissions', permission: 'edit_permissions', label: 'Manage Permissions', icon: KeyRound, variant: 'outline', run: bulkManageUserPermissions },
    {
        key: 'verify',
        permission: 'verify',
        label: 'Verify & Send Credentials',
        icon: MailCheck,
        class: 'bg-green-600 hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600',
        run: bulkVerifyCredentials,
    },
    { key: 'activate', permission: 'toggle_active', label: 'Activate', icon: ToggleLeft, variant: 'outline', appliesTo: (user) => !isActive(user), run: (targets) => bulkToggleActiveUsers(targets, 1) },
    { key: 'deactivate', permission: 'toggle_active', label: 'Deactivate', icon: ToggleRight, variant: 'outline', appliesTo: isActive, run: (targets) => bulkToggleActiveUsers(targets, 0) },
    { key: 'restore', permission: 'destroy', label: 'Restore', icon: RotateCcw, variant: 'outline', appliesTo: isDeleted, run: (targets) => bulkDeleteUsers(targets, 'restore') },
    { key: 'delete', permission: 'destroy', label: 'Delete', icon: Trash2, variant: 'destructive', appliesTo: (user) => !isDeleted(user), run: (targets) => bulkDeleteUsers(targets, 'delete') },
];

const permittedBulkActions = computed(() => bulkActions.filter((action) => hasPermission(`${slug.value}.${action.permission}`)));

/** The permitted actions for this selection, each with the selected users it would act on. */
const bulkActionsFor = (rows: { original: unknown }[]) => {
    const selected = rows.map((row) => row.original as UserRow);

    return permittedBulkActions.value
        .map((action) => ({ action, targets: action.appliesTo ? selected.filter(action.appliesTo) : selected }))
        .filter(({ targets }) => targets.length > 0);
};

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: slug.value,
    },
];

// --- Fetching ---
/**
 * The active search and filters as request params — shared by the list fetch and the
 * report export, so an export always holds what the filtered list shows.
 */
const filterParams = (): Record<string, string> => {
    const params: Record<string, string> = {};

    if (searchQuery.value.trim())               params.search_string     = searchQuery.value.trim();
    if (bulkLookup.value) {
        // One delimited string rather than an array, so it rides in the export's query string
        // as well; the server splits it on the same separators (ListRequest).
        params.search_terms = bulkLookup.value.terms.join('\n');
        if (bulkLookup.value.exact)               params.exact_match       = '1';
        if (bulkLookup.value.focus)               params.search_term_focus = bulkLookup.value.focus;
    }
    if (filters.value.type)                     params.type              = filters.value.type;
    if (filters.value.department_id)            params.department_id     = filters.value.department_id;
    // '' means "no filter"; '0' is a real choice, so these cannot be falsy tests.
    if (filters.value.status !== '')            params.is_active         = filters.value.status;
    if (filters.value.credential_status !== '') params.credential_status = filters.value.credential_status;
    if (filters.value.credential_access !== '') params.credential_access = filters.value.credential_access;

    return params;
};

const exportReport = (report: CredentialReportOption) => exportCredentialReport(report, filterParams());

const { pagination, isFetching, queueFetch, onPaginationChange } = useServerListing({
    listing: () => users.value,
    url: () => `/${slug.value}`,
    params: filterParams,
    visit: () => {
        const reloadMatches = bulkLookup.value !== null && matchesStale.value;

        return {
            only: reloadMatches ? [slug.value, 'search_term_matches'] : [slug.value],
            // A superseded visit never succeeds, so the counts stay stale until one that carried them lands.
            onSuccess: () => { if (reloadMatches) matchesStale.value = false; },
        };
    },
});

/** Apply a bulk lookup from page one, listing every entry's matches. */
const runBulkLookup = (lookup: BulkUserLookup) => {
    bulkLookup.value = { ...lookup, focus: null };
    matchesStale.value = true;
    queueFetch(0);
};

/** Narrow the list to one entry's matches, or back to all of them; the counts are unchanged. */
const focusBulkTerm = (term: string | null) => {
    if (!bulkLookup.value || bulkLookup.value.focus === term) return;
    bulkLookup.value.focus = term;
    queueFetch(0);
};

const clearBulkLookup = () => {
    if (!bulkLookup.value) return;
    bulkLookup.value = null;
    queueFetch(0);
};

/**
 * The bulk lookup replaces the single search while open — two searches over the same
 * columns would only narrow each other — and closing it drops whatever it applied.
 */
const toggleBulkSearch = () => {
    bulkSearchOpen.value = !bulkSearchOpen.value;
    if (bulkSearchOpen.value) {
        searchQuery.value = '';
    } else {
        clearBulkLookup();
    }
};

// Typing reloads once it pauses; spacing alone changes nothing the server would see.
watch(() => searchQuery.value.trim(), () => queueFetch(500));

watch(
    filters,
    () => {
        // Per-entry counts are taken under the other filters, so they move with them.
        matchesStale.value = true;
        queueFetch(300);
    },
    { deep: true },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />

        <div class="flex flex-1 flex-col gap-4 p-4">
            <Card class="gap-0 py-0">
                <!-- Title, live result count and page-level actions -->
                <CardHeader class="flex flex-col gap-4 border-b px-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6 [.border-b]:pb-5">
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-lg leading-none font-semibold tracking-tight">Users</h1>
                            <span
                                class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground"
                                aria-live="polite">
                                {{ resultSummary }}
                            </span>
                        </div>
                        <CardDescription>
                            Manage system accounts, their roles and permissions, and the credentials sent to them.
                        </CardDescription>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Credential reports over the current filters (UserCredentialReport) -->
                        <DropdownMenu v-if="canExport">
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" class="cursor-pointer">
                                    <FileDown /> Export <ChevronDown class="opacity-60" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-72">
                                <DropdownMenuLabel class="text-xs font-normal text-muted-foreground">
                                    Reports use the current search and filters
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-for="report in credentialReports"
                                    :key="report.value"
                                    class="cursor-pointer flex-col items-start gap-0.5"
                                    @select="exportReport(report)">
                                    <span class="text-sm font-medium">{{ report.name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ report.description }}</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <template v-if="canCreate">
                            <Button variant="outline" class="cursor-pointer" @click="bulkImportUsers">
                                <Upload /> Bulk import
                            </Button>
                            <Button class="cursor-pointer" @click="createUser">
                                <UserPlus /> Create user
                            </Button>
                        </template>
                    </div>
                </CardHeader>

                <!-- Search, bulk lookup and filters -->
                <CardContent class="flex flex-col gap-3 border-b px-4 py-4 sm:px-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <ListSearch
                            v-if="!bulkSearchOpen"
                            id="user-search"
                            v-model="searchQuery"
                            label="Search users"
                            placeholder="Search username or email..." />
                        <!-- Many usernames/emails at once: opens "Search multiple users" in place of the single search -->
                        <Button
                            type="button"
                            class="cursor-pointer self-start sm:self-auto"
                            :variant="bulkSearchOpen ? 'default' : 'outline'"
                            :aria-expanded="bulkSearchOpen"
                            aria-controls="bulk-user-search"
                            @click="toggleBulkSearch">
                            <TextSearch /> Bulk search
                        </Button>
                    </div>

                    <BulkUserSearch
                        v-if="bulkSearchOpen"
                        id="bulk-user-search"
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

                <!-- The list; dimmed while a reload is in flight -->
                <CardContent
                    class="px-4 py-4 transition-opacity duration-200 sm:px-6"
                    :class="{ 'opacity-60': isFetching }"
                    :aria-busy="isFetching">
                    <Datatable
                        :data="users.data"
                        :columns="columns"
                        :pagination="pagination"
                        :show-selection-column="true"
                        :enable-search="false"
                        :enable-row-click="true"
                        :row-click="openUserDetails"
                        :empty-message="emptyState.message"
                        :empty-description="emptyState.description"
                        export-file-name="users_list"
                        @update:pagination="onPaginationChange">
                        <template #bulk-actions="{ selectedRows }">
                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    v-for="{ action, targets } in bulkActionsFor(selectedRows)"
                                    :key="action.key"
                                    size="sm"
                                    :variant="action.variant"
                                    class="cursor-pointer"
                                    :class="action.class"
                                    @click="action.run(targets)">
                                    <component :is="action.icon" />
                                    {{ action.label }}
                                    <!-- Says how many it would act on when that is not the whole selection -->
                                    <span v-if="targets.length !== selectedRows.length" class="tabular-nums opacity-70">
                                        ({{ targets.length }})
                                    </span>
                                </Button>
                            </div>
                        </template>
                    </Datatable>
                </CardContent>
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

        <!-- Top pane: one entry of a user's activity, opened over the user pane -->
        <TopPane
            :open="topPaneVisible"
            :title="topPaneTitle"
            :loading="topPaneLoading"
            :error="topPaneError"
            :content-component="topPaneContentComponent"
            :component-props="topPaneComponentProps"
            @update:open="(v) => { if (!v && !topPaneLoading) closePane('top') }" />
    </AppLayout>
</template>
