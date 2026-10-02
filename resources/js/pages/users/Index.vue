<script setup lang="ts">
import { ref, watch, computed, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import RightPane from '@/components/RightPane.vue';
import { Button } from "@/components/ui/button";
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectItem, SelectValue } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import BulkUserSearch from '@/components/forms/users/BulkUserSearch.vue';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import {
    useUsers,
    type BulkUserLookup,
    type CredentialReportOption,
    type SearchTermMatch,
    type UserCredentials,
} from '@/composables/users';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { badge } from '@/lib/directoryBadges';
import { UserRoundCog, KeyRound, ToggleLeft, ToggleRight, Trash2, RotateCcw, SlidersHorizontal, X, MailCheck, Upload, FileDown, TextSearch } from 'lucide-vue-next';

type UsersPagination = {
    current_page: number
    per_page: number
    total: number
    data: unknown[]
}
const page = usePage();
const { slug, hasPermission, canCreate } = useModulePermissions();
// Initialize with empty data - no data loaded on mount
const users = computed(() => {
    const propsUsers = (page.props as any).users as UsersPagination | undefined;
    if (!propsUsers) {
        return {
            current_page: 1,
            per_page: 10,
            total: 0,
            data: []
        } as UsersPagination;
    }
    return propsUsers;
});
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
} = useUsers();
const columnHelper = createColumnHelper();
const pagination = ref({
	current_page: users.value.current_page,
	per_page: Number(users.value.per_page),
	total: users.value.total
})
const searchQuery = ref('')
const hasInitialized = ref(false)
const isFirstLoad = ref(true)

// --- Bulk lookup ("Search multiple users") ---
/** The applied lookup, plus the one entry the list is narrowed to (null: every entry's matches). */
const bulkLookup = ref<(BulkUserLookup & { focus: string | null }) | null>(null)
const bulkSearchOpen = ref(false)
/**
 * Whether the per-entry counts must be reloaded with the list. They depend on the entries
 * and the other filters, not on paging or on which entry is singled out — so those
 * reloads leave the server's lazy `search_term_matches` prop, and its aggregate, out.
 */
const matchesStale = ref(false)

const searchTermMatches = computed<SearchTermMatch[]>(
    () => ((page.props as any).search_term_matches as SearchTermMatch[] | undefined) ?? [],
)
const maxSearchTerms = computed(() => Number((page.props as any).max_search_terms) || 100)

// --- Filters ---
type Option = { value: number; name: string }
type FilterOptions = {
    user_types: Option[]
    departments: { id: number; name: string }[]
    credential_statuses: Option[]
    credential_accesses: Option[]
}

const filterOptions = computed<FilterOptions>(() => {
    const opts = (page.props as any).filter_options as Partial<FilterOptions> | undefined
    return {
        user_types: opts?.user_types ?? [],
        departments: opts?.departments ?? [],
        credential_statuses: opts?.credential_statuses ?? [],
        credential_accesses: opts?.credential_accesses ?? [],
    }
})

/** The reports the export menu offers — the server's list (`UserCredentialReport`). */
const credentialReports = computed<CredentialReportOption[]>(
    () => ((page.props as any).credential_reports as CredentialReportOption[] | undefined) ?? [],
)

const VC_EMPLOYEE_TYPE = '1' // UserType::VC_EMPLOYEE
const FILTER_ALL = 'all'     // sentinel value — means "no filter applied"

const emptyFilters = () => ({ type: '', department_id: '', status: '', credential_status: '', credential_access: '' })
const filters = ref(emptyFilters())

const isDepartmentFilterEnabled = computed(() => filters.value.type === VC_EMPLOYEE_TYPE)

const filtersActive = computed(() => Object.values(filters.value).some((value) => value !== ''))

const typeModel = computed({
    get: () => filters.value.type || FILTER_ALL,
    set: (v: string | undefined) => {
        const val = v === FILTER_ALL ? '' : (v ?? '')
        filters.value.type = val
        if (val !== VC_EMPLOYEE_TYPE) filters.value.department_id = ''
    },
})
const departmentModel = computed({
    get: () => filters.value.department_id || FILTER_ALL,
    set: (v: string | undefined) => { filters.value.department_id = v === FILTER_ALL ? '' : (v ?? '') },
})
/** Selects bind to a sentinel rather than '' so "All" is a real option; '0' is a real value. */
const asSelectModel = (key: 'status' | 'credential_status' | 'credential_access') => computed({
    get: () => filters.value[key] !== '' ? filters.value[key] : FILTER_ALL,
    set: (v: string | undefined) => { filters.value[key] = v === FILTER_ALL ? '' : (v ?? '') },
})
const statusModel = asSelectModel('status')
const credentialStatusModel = asSelectModel('credential_status')
const credentialAccessModel = asSelectModel('credential_access')

const clearFilters = () => {
    filters.value = emptyFilters()
}

const statusOptions = [
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' },
]
// ----------------

const baseColumns: any[] = [
  columnHelper.accessor('id', {
    header: 'ID',
  }),
  columnHelper.accessor('username', {
    header: 'Username',
  }),
  columnHelper.accessor('email', {
    header: 'Email',
  }),
  columnHelper.accessor('type_label', {
    header: 'Type',
    cell: (info: any) => info.getValue() ?? '—',
  }),
  columnHelper.accessor('department', {
    header: 'Department',
    cell: (info: any) => info.getValue() ?? '—',
  }),
  columnHelper.accessor('is_active', {
    header: 'Status',
    cell: (info: any) => {
      const active = Number(info.getValue()) !== 0;
      return h(
        'span',
        {
          class: [
            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
            active
              ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
              : 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
          ],
        },
        active ? 'Active' : 'Inactive',
      );
    },
  }),
  columnHelper.accessor((row: any) => (row.credentials as UserCredentials | undefined)?.status_label ?? '—', {
    id: 'credentials',
    header: 'Password',
    // Label and colour come from the server (CredentialStatus); the dates behind them
    // ride along as a hover title so the column stays one badge wide.
    cell: (info: any) => {
      const credentials = info.row.original?.credentials as UserCredentials | undefined
      if (!credentials) return '—'

      const details = [
        credentials.sent_at && `Sent: ${credentials.sent_at}`,
        credentials.temporary_expires_at && `Temporary expires: ${credentials.temporary_expires_at}`,
        credentials.password_changed_at && `Updated: ${credentials.password_changed_at}`,
        `Last login: ${credentials.last_login_at ?? 'never'}`,
      ].filter(Boolean).join('\n')

      return h('span', { title: details }, [badge(credentials.status_label, credentials.status_color)])
    },
  }),
  columnHelper.accessor('created_at', {
    header: 'Created',
    // The server sends the label; `created_at_value` carries the sortable timestamp.
    sortingFn: (a: any, b: any) =>
      String(a.original?.created_at_value ?? '').localeCompare(String(b.original?.created_at_value ?? '')),
    cell: (info: any) => info.getValue() ?? '—',
  }),
]

const handlerMap: Record<string, Function> = {
  edit: editUser,
  update: editUser,
  delete: deleteUser,
  destroy: deleteUser,
  edit_roles: (user: any) => manageUserRoles(user),
  edit_permissions: (user: any) => manageUserPermissions(user),
  verify: (user: any) => verifyUsers([user]),
  toggle_active: (user: any) => toggleActiveUser(user),
  // The action opens the same pane a row click does, straight onto the mapping tab.
  account_mapping: (user: any) => openUserPane(user, 'account_mapping'),
}

/** A row click opens the pane on the details tab; the mapping action opens it on the other. */
const openUserDetails = (user: any) => openUserPane(user, 'details')

const columns = computed(() => {
  const subModules = page.props.sub_modules
    .filter((m: any) => hasPermission(m.slug) && m.slug.split('.')[1] !== 'create')
    .map((m: any) => {
      const key = m.slug.split('.')[1];
      const entry: any = { ...m, handler: handlerMap[key] };
      if (key === 'toggle_active') {
        entry.dynamicProps = (item: any) => Number(item.is_active) !== 0
          ? { name: 'Deactivate', icon: 'ToggleRight', color: 'orange' }
          : { name: 'Activate',   icon: 'ToggleLeft',  color: 'green'  };
      }
      if (key === 'destroy') {
        entry.dynamicProps = (item: any) => item.deleted_at
          ? { name: 'Restore', icon: 'RotateCcw', color: 'green' }
          : { name: 'Delete',  icon: 'Trash2',    color: 'red'   };
      }
      if (key === 'account_mapping') {
        // Only the account-scoped types are mapped to accounts and branches; the
        // server decides which those are (UserType::allowsAccountMapping).
        entry.shouldRender = (item: any) => item.allows_account_mapping === true;
      }
      return entry;
    });

  return [...baseColumns, createActionColumn([...subModules])]
})

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Users',
    href: slug.value,
  },
];

/**
 * The active search and filters as request params — shared by the list fetch and the
 * report export, so an export always holds what the filtered list shows.
 */
const filterParams = (): Record<string, string> => {
  const params: Record<string, string> = {}

  if (searchQuery.value.trim())               params.search_string     = searchQuery.value.trim()
  if (bulkLookup.value) {
    // One delimited string rather than an array, so it rides in the export's query string
    // as well; the server splits it on the same separators (ListRequest).
    params.search_terms = bulkLookup.value.terms.join('\n')
    if (bulkLookup.value.exact)               params.exact_match       = '1'
    if (bulkLookup.value.focus)               params.search_term_focus = bulkLookup.value.focus
  }
  if (filters.value.type)                     params.type              = filters.value.type
  if (filters.value.department_id)            params.department_id     = filters.value.department_id
  // '' means "no filter"; '0' is a real choice, so these cannot be falsy tests.
  if (filters.value.status !== '')            params.is_active         = filters.value.status
  if (filters.value.credential_status !== '') params.credential_status = filters.value.credential_status
  if (filters.value.credential_access !== '') params.credential_access = filters.value.credential_access

  return params
}

const exportReport = (report: CredentialReportOption) => exportCredentialReport(report, filterParams())

// Function to fetch data from server
const fetchUsers = () => {
  const params: Record<string, any> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
    ...filterParams(),
  }
  const reloadMatches = bulkLookup.value !== null && matchesStale.value

  router.get(
    `/${slug.value}`,
    params,
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: reloadMatches ? [slug.value, 'search_term_matches'] : [slug.value],
      // A superseded visit never succeeds, so the counts stay stale until one that carried them lands.
      onSuccess: () => { if (reloadMatches) matchesStale.value = false },
    }
  )
}

/** Apply a bulk lookup from page one, listing every entry's matches. */
const runBulkLookup = (lookup: BulkUserLookup) => {
  bulkLookup.value = { ...lookup, focus: null }
  matchesStale.value = true
  hasInitialized.value = true
  pagination.value.current_page = 1
  fetchUsers()
}

/** Narrow the list to one entry's matches, or back to all of them; the counts are unchanged. */
const focusBulkTerm = (term: string | null) => {
  if (!bulkLookup.value || bulkLookup.value.focus === term) return
  bulkLookup.value.focus = term
  pagination.value.current_page = 1
  fetchUsers()
}

const clearBulkLookup = () => {
  if (!bulkLookup.value) return
  bulkLookup.value = null
  pagination.value.current_page = 1
  fetchUsers()
}

/**
 * The bulk lookup replaces the single search while open — two searches over the same
 * columns would only narrow each other — and closing it drops whatever it applied.
 */
const toggleBulkSearch = () => {
  bulkSearchOpen.value = !bulkSearchOpen.value
  if (bulkSearchOpen.value) {
    searchQuery.value = ''
  } else {
    clearBulkLookup()
  }
}

// Debounced data fetching for search query changes
const searchTimeout = ref<number | null>(null)
watch(
    searchQuery,
    (newQuery, oldQuery) => {
        // Only fetch if user has interacted (not on initial mount)
        if (!hasInitialized.value && oldQuery === undefined) return

        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value)
        }
        searchTimeout.value = window.setTimeout(() => {
            // Reset to first page when searching
            pagination.value.current_page = 1
            fetchUsers()
        }, 500)
    },
    { immediate: false }
)

// Keep local pagination in sync when server returns new users payload
// Use a flag to prevent infinite loops when Datatable's watcher updates pagination
const isUpdatingFromServer = ref(false)
watch(
    users,
    (next) => {
        if (!next) return
        isUpdatingFromServer.value = true
        pagination.value.current_page = next.current_page
        pagination.value.per_page = Number(next.per_page)
        pagination.value.total = next.total

        // Mark that we've loaded data at least once
        if (isFirstLoad.value && next.total > 0) {
            isFirstLoad.value = false
        }

        // Reset flag after a tick to allow Datatable to process the update
        // Use a longer timeout to prevent Datatable's watcher from triggering
        setTimeout(() => {
            isUpdatingFromServer.value = false
        }, 300)
    }
)

// Debounced data fetching for pagination changes
const fetchTimeout = ref<number | null>(null)
const isUserPaginationChange = ref(false)
watch(
    () => [pagination.value.current_page, pagination.value.per_page],
    ([currentPage, perPage], _prev) => {
        // Only fetch if user has interacted (not on initial mount)
        if (!hasInitialized.value) return
        // Don't fetch if this is an update from server response
        if (isUpdatingFromServer.value) return

        if (fetchTimeout.value) {
            clearTimeout(fetchTimeout.value)
        }

        // Mark that this is a user-initiated pagination change
        isUserPaginationChange.value = true

        fetchTimeout.value = window.setTimeout(() => {
            pagination.value.current_page = Number(currentPage) || 1
            pagination.value.per_page = Number(perPage) || 10

            // Make our request with search parameter
            // This will happen before Datatable's watcher can trigger
            fetchUsers()

            // Reset flag after request is made
            setTimeout(() => {
                isUserPaginationChange.value = false
            }, 500)
        }, 50) // Shorter timeout to beat Datatable's watcher
    },
    { immediate: false }
)

// Debounced fetch when any filter dropdown changes
const filterTimeout = ref<number | null>(null)
watch(
    filters,
    () => {
        if (filterTimeout.value) clearTimeout(filterTimeout.value)
        filterTimeout.value = window.setTimeout(() => {
            pagination.value.current_page = 1
            hasInitialized.value = true
            // Per-entry counts are taken under the other filters, so they move with them.
            matchesStale.value = true
            fetchUsers()
        }, 300)
    },
    { deep: true, immediate: false }
)
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Users" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <div class="flex flex-col gap-3 mb-4">
                <!-- Top row: create + search -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="flex items-center gap-2">
                        <Button class="cursor-pointer" v-if="canCreate" :onClick="createUser">Create</Button>
                        <Button class="cursor-pointer" v-if="canCreate" variant="outline" :onClick="bulkImportUsers">
                            <Upload class="w-4 h-4 mr-1" /> Bulk Import
                        </Button>
                        <!-- Credential reports over the current filters (UserCredentialReport) -->
                        <DropdownMenu v-if="hasPermission(`${slug}.export`) && credentialReports.length">
                            <DropdownMenuTrigger as-child>
                                <Button class="cursor-pointer" variant="outline">
                                    <FileDown class="w-4 h-4 mr-1" /> Export
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-72">
                                <DropdownMenuLabel class="text-xs text-[var(--color-text-muted)]">
                                    Reports use the current search and filters
                                </DropdownMenuLabel>
                                <DropdownMenuItem
                                    v-for="report in credentialReports"
                                    :key="report.value"
                                    class="cursor-pointer flex-col items-start gap-0.5"
                                    @select="exportReport(report)">
                                    <span class="text-sm font-medium">{{ report.name }}</span>
                                    <span class="text-xs text-[var(--color-text-muted)]">{{ report.description }}</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    <div class="flex w-full sm:w-auto items-center gap-2">
                        <div v-if="!bulkSearchOpen" class="relative w-full sm:w-64">
                            <label class="sr-only" for="user-search">Search users</label>
                            <input
                                id="user-search"
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search username or email..."
                                class="border border-[var(--color-border-strong)] rounded-md text-sm bg-[var(--color-surface)] text-[var(--color-text)] focus:ring-2 focus:ring-opacity-50 focus:border-transparent w-full px-4 py-2 pr-8"
                                :style="{ '--tw-ring-color': 'var(--primary-color)' }"
                                @input="hasInitialized = true" />
                            <button
                                v-if="searchQuery"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-opacity-50"
                                :style="{ '--tw-ring-color': 'var(--primary-color)' }"
                                aria-label="Clear search"
                                @click="searchQuery = ''">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <!-- Many usernames/emails at once: opens "Search multiple users" in place of the single search -->
                        <Button
                            type="button"
                            class="cursor-pointer shrink-0"
                            :variant="bulkSearchOpen ? 'default' : 'outline'"
                            :aria-expanded="bulkSearchOpen"
                            aria-controls="bulk-user-search"
                            @click="toggleBulkSearch">
                            <TextSearch class="w-4 h-4 mr-1" /> Bulk search
                        </Button>
                    </div>
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

                <!-- Filter row -->
                <div class="flex flex-wrap items-center gap-2">
                    <SlidersHorizontal class="w-4 h-4 shrink-0 text-[var(--color-text-muted)]" aria-hidden="true" />

                    <!-- User Type -->
                    <Select v-model="typeModel">
                        <SelectTrigger class="h-8 w-44 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                    All types
                                </SelectItem>
                                <SelectItem
                                    v-for="opt in filterOptions.user_types"
                                    :key="String(opt.value)"
                                    :value="String(opt.value)"
                                    class="text-xs">
                                    {{ opt.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <!-- Department (only applicable for VC Employee type) -->
                    <Select v-model="departmentModel" :disabled="!isDepartmentFilterEnabled">
                        <SelectTrigger
                            class="h-8 w-44 text-xs"
                            :class="!isDepartmentFilterEnabled ? 'opacity-50 cursor-not-allowed' : ''"
                            :title="!isDepartmentFilterEnabled ? 'Select VC Employee type to filter by department' : undefined">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                    All departments
                                </SelectItem>
                                <SelectItem
                                    v-for="dept in filterOptions.departments"
                                    :key="String(dept.id)"
                                    :value="String(dept.id)"
                                    class="text-xs">
                                    {{ dept.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <!-- Status -->
                    <Select v-model="statusModel">
                        <SelectTrigger class="h-8 w-36 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                    All statuses
                                </SelectItem>
                                <SelectItem
                                    v-for="opt in statusOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                    class="text-xs">
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <!-- Password / credential status -->
                    <Select v-model="credentialStatusModel">
                        <SelectTrigger class="h-8 w-44 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                    Any password status
                                </SelectItem>
                                <SelectItem
                                    v-for="opt in filterOptions.credential_statuses"
                                    :key="String(opt.value)"
                                    :value="String(opt.value)"
                                    class="text-xs">
                                    {{ opt.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <!-- Whether sent credentials have been used -->
                    <Select v-model="credentialAccessModel">
                        <SelectTrigger class="h-8 w-44 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                    Any credential access
                                </SelectItem>
                                <SelectItem
                                    v-for="opt in filterOptions.credential_accesses"
                                    :key="String(opt.value)"
                                    :value="String(opt.value)"
                                    class="text-xs">
                                    {{ opt.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <!-- Clear filters -->
                    <Button
                        v-if="filtersActive"
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
                        @click="clearFilters">
                        <X class="w-3 h-3 mr-1" />
                        Clear
                    </Button>
                </div>
            </div>
            <Datatable
                :data="users.data"
                :columns="columns"
                :pagination="pagination"
                :show-selection-column="true"
                :enable-search="false"
                :enable-row-click="true"
                :row-click="openUserDetails"
                :empty-message="bulkLookup ? 'No users match these entries' : 'No users found'"
                empty-description="System users will appear here. Use search, pagination, or change rows per page to load data."
                export-file-name="users_list"
                @update:pagination="(newPagination: typeof pagination) => { hasInitialized = true; pagination = newPagination }">
                <template #bulk-actions="{ selectedRows }">
                    <Button
                        class="cursor-pointer"
                        v-if="hasPermission(`${slug}.edit_roles`)"
                        size="sm"
                        @click="bulkManageUserRoles(selectedRows.map((r: any) => r.original))">
                        Manage Roles <UserRoundCog class="w-4 h-4 ml-1" />
                    </Button>
                    <Button
                        class="cursor-pointer"
                        v-if="hasPermission(`${slug}.edit_permissions`)"
                        size="sm"
                        @click="bulkManageUserPermissions(selectedRows.map((r: any) => r.original))">
                        Manage Permissions <KeyRound class="w-4 h-4 ml-1" />
                    </Button>
                    <Button
                        class="cursor-pointer bg-green-600 hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600"
                        v-if="hasPermission(`${slug}.verify`)"
                        size="sm"
                        @click="bulkVerifyCredentials(selectedRows.map(r => r.original))">
                        Verify &amp; Send Credentials <MailCheck class="w-4 h-4 ml-1" />
                    </Button>
                    <template v-if="hasPermission(`${slug}.toggle_active`)">
                        <Button
                            v-if="selectedRows.some(r => Number(r.original.is_active) === 0)"
                            class="cursor-pointer"
                            size="sm"
                            @click="bulkToggleActiveUsers(selectedRows.map(r => r.original).filter(u => Number(u.is_active) === 0), 1)">
                            Activate <ToggleLeft class="w-4 h-4 ml-1" />
                        </Button>
                        <Button
                            v-if="selectedRows.some(r => Number(r.original.is_active) !== 0)"
                            class="cursor-pointer"
                            size="sm"
                            @click="bulkToggleActiveUsers(selectedRows.map(r => r.original).filter(u => Number(u.is_active) !== 0), 0)">
                            Deactivate <ToggleRight class="w-4 h-4 ml-1" />
                        </Button>
                    </template>
                    <template v-if="hasPermission(`${slug}.destroy`)">
                        <Button
                            v-if="selectedRows.some(r => !r.original.deleted_at)"
                            class="cursor-pointer"
                            size="sm"
                            variant="destructive"
                            @click="bulkDeleteUsers(selectedRows.map(r => r.original).filter(u => !u.deleted_at), 'delete')">
                            Delete <Trash2 class="w-4 h-4 ml-1" />
                        </Button>
                        <Button
                            v-if="selectedRows.some(r => r.original.deleted_at)"
                            class="cursor-pointer"
                            size="sm"
                            @click="bulkDeleteUsers(selectedRows.map(r => r.original).filter(u => u.deleted_at), 'restore')">
                            Restore <RotateCcw class="w-4 h-4 ml-1" />
                        </Button>
                    </template>
                </template>
            </Datatable>
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
