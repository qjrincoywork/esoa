<script setup lang="ts">
import { ref, watch, computed, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectItem, SelectValue } from '@/components/ui/select';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useUsers } from '@/composables/users';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { UserRoundCog, ToggleLeft, ToggleRight, Trash2, RotateCcw, SlidersHorizontal, X, MailCheck, Upload } from 'lucide-vue-next';

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
const { createUser, bulkImportUsers, editUser, deleteUser, manageUserRoles, bulkManageUserRoles, bulkToggleActiveUsers, bulkDeleteUsers, verifyUsers, bulkVerifyCredentials, toggleActiveUser } = useUsers();
const columnHelper = createColumnHelper();
const pagination = ref({
	current_page: users.value.current_page,
	per_page: Number(users.value.per_page),
	total: users.value.total
})
const searchQuery = ref('')
const hasInitialized = ref(false)
const isFirstLoad = ref(true)

// --- Filters ---
type FilterOptions = {
    user_types: { value: number; name: string }[]
    departments: { id: number; name: string }[]
}

const filterOptions = computed<FilterOptions>(() => {
    const opts = (page.props as any).filter_options as FilterOptions | undefined
    return {
        user_types: opts?.user_types ?? [],
        departments: opts?.departments ?? [],
    }
})

const VC_EMPLOYEE_TYPE = '1' // UserType::VC_EMPLOYEE
const FILTER_ALL = 'all'     // sentinel value — means "no filter applied"

const filters = ref({ type: '', department_id: '', status: '' })

const isDepartmentFilterEnabled = computed(() => filters.value.type === VC_EMPLOYEE_TYPE)

const filtersActive = computed(() =>
    filters.value.type !== '' || filters.value.department_id !== '' || filters.value.status !== ''
)

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
const statusModel = computed({
    get: () => filters.value.status !== '' ? filters.value.status : FILTER_ALL,
    set: (v: string | undefined) => { filters.value.status = v === FILTER_ALL ? '' : (v ?? '') },
})

const clearFilters = () => {
    filters.value = { type: '', department_id: '', status: '' }
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
]

const handlerMap: Record<string, Function> = {
  edit: editUser,
  update: editUser,
  delete: deleteUser,
  destroy: deleteUser,
  edit_roles: (user: any) => manageUserRoles(user),
  verify: (user: any) => verifyUsers([user]),
  toggle_active: (user: any) => toggleActiveUser(user),
}

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

// Function to fetch data from server
const fetchUsers = () => {
  const params: Record<string, any> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
  }

  if (searchQuery.value.trim())      params.search_string  = searchQuery.value.trim()
  if (filters.value.type)            params.type           = filters.value.type
  if (filters.value.department_id)   params.department_id  = filters.value.department_id
  if (filters.value.status !== '')   params.is_active      = filters.value.status

  router.get(
    `/${slug.value}`,
    params,
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: [slug.value],
    }
  )
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
                    </div>
                    <div class="relative w-full sm:w-64">
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
                </div>

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
                empty-message="No users found"
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
    </AppLayout>
</template>
