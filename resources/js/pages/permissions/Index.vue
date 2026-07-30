<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { usePermissions } from '@/composables/permissions';
import { useModulePermissions } from '@/composables/useModulePermissions';

type PermissionsPagination = {
    current_page: number
    per_page: number
    total: number
    data: unknown[]
}

const page = usePage();
const { canCreate, slug, hasPermission } = useModulePermissions();
const permissions = computed(() => (page.props as any).permissions as PermissionsPagination);
const { createPermission, editPermission, deletePermission } = usePermissions();
const columnHelper = createColumnHelper();
const pagination = ref({
	current_page: permissions.value.current_page,
	per_page: Number(permissions.value.per_page),
	total: permissions.value.total
})
const searchQuery = ref('')
// Guards against triggering a fetch on initial mount and while syncing server responses
const hasInitialized = ref(false)
const isUpdatingFromServer = ref(false)
const baseColumns = [
  columnHelper.accessor('id', {
    header: 'ID',
  }),
  columnHelper.accessor('name', {
    header: 'Name',
  }),
  columnHelper.accessor('guard_name', {
    header: 'Guard Name',
  }),
]

const handlerMap: Record<string, Function> = {
  edit: editPermission,
  update: editPermission,
  delete: deletePermission,
  destroy: deletePermission,
}

const columns = computed(() => {
  const subModules = page.props.sub_modules
    .filter((m: any) => hasPermission(m.slug) && m.slug.split('.')[1] != 'create')
    .map((m: any) => ({
      ...m,
      handler: handlerMap[m.slug.split('.')[1]],
    }))

  return subModules.length
    ? [...baseColumns, createActionColumn(subModules)]
    : baseColumns
})

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Permissions',
        href: 'permissions',
    },
];
// Build the server request, keeping pagination and the active search term together
const fetchPermissions = () => {
    const params: Record<string, string | number> = {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
    }

    const search = searchQuery.value.trim()
    if (search) params.name = search

    router.get('/permissions', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['permissions'],
    })
}

// Debounced server-side search by name; resets to the first page on each new query
const searchTimeout = ref<number | null>(null)
watch(
    searchQuery,
    (_newQuery, oldQuery) => {
        // Skip the initial mount so we don't fire a redundant request
        if (!hasInitialized.value && oldQuery === undefined) return
        if (searchTimeout.value) clearTimeout(searchTimeout.value)
        searchTimeout.value = window.setTimeout(() => {
            pagination.value.current_page = 1
            fetchPermissions()
        }, 500)
    },
    { immediate: false }
)

// Debounced data fetching for pagination changes
const fetchTimeout = ref<number | null>(null)
watch(
    () => [pagination.value.current_page, pagination.value.per_page],
    () => {
        if (!hasInitialized.value) return
        // Ignore updates coming from the server sync watcher below
        if (isUpdatingFromServer.value) return
        if (fetchTimeout.value) clearTimeout(fetchTimeout.value)
        fetchTimeout.value = window.setTimeout(fetchPermissions, 200)
    },
    { immediate: false }
)
// Keep local pagination in sync when server returns new permissions payload
watch(
    permissions,
    (next) => {
        if (!next) return
        isUpdatingFromServer.value = true
        pagination.value.current_page = next.current_page
        pagination.value.per_page = Number(next.per_page)
        pagination.value.total = next.total
        // Release the guard after the pagination watcher has settled
        setTimeout(() => { isUpdatingFromServer.value = false }, 0)
    }
)
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Permissions" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                <Button :onClick="createPermission">Create</Button>
                <div class="relative w-full sm:w-64">
                    <label class="sr-only" for="permission-search">Search permissions</label>
                    <input
                        id="permission-search"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name..."
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
            <Datatable
                :data="permissions.data"
                :columns="columns"
                :pagination="pagination"
                :enable-search="false"
                empty-message="No permissions found"
                empty-description="System permissions will appear here"
                export-file-name="permissions_list"
                @update:pagination="(newPagination) => { hasInitialized = true; pagination = newPagination }">
            </Datatable>
        </div>
    </AppLayout>
</template>
