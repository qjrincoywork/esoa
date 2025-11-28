<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useNavigations } from '@/composables/navigations';
import { useModulePermissions } from '@/composables/useModulePermissions';

type NavigationsPagination = {
    current_page: number
    per_page: number
    total: number
    data: unknown[]
}
const page = usePage();
const { slug, authPermissions, hasPermission, canCreate, canEdit, canDelete } = useModulePermissions();
// Initialize with empty data - no data loaded on mount
const navigations = computed(() => {
    const propsNavigations = (page.props as any).navigation_list as NavigationsPagination | undefined;
    if (!propsNavigations) {
        return {
            current_page: 1,
            per_page: 10,
            total: 0,
            data: []
        } as NavigationsPagination;
    }
    return propsNavigations;
});
const { createNavigation, editNavigation, deleteNavigation } = useNavigations();
const columnHelper = createColumnHelper();
const pagination = ref({
	current_page: navigations.value.current_page,
	per_page: Number(navigations.value.per_page),
	total: navigations.value.total
})
const searchQuery = ref('')
const hasInitialized = ref(false)
const isFirstLoad = ref(true)  // Track if this is the very first data load
const baseColumns: any[] = [
  columnHelper.accessor('id', {
    header: 'ID',
  }),
  columnHelper.accessor('name', {
    header: 'Name',
  }),
  columnHelper.accessor('label', {
    header: 'Label',
  }),
  columnHelper.accessor('icon', {
    header: 'Icon',
  }),
  columnHelper.accessor('created_by', {
    header: 'Created By',
  }),
  columnHelper.accessor('status', {
    header: 'Status',
  }),
]

const columns = computed(() => {
  const cols = [...baseColumns];
  const showEdit = canEdit.value;
  const showDelete = canDelete.value;

  if (showEdit || showDelete) {
    cols.push(createActionColumn({
      basePath: `/${slug.value}`,
      onEdit: showEdit ? editNavigation : undefined,
      onDelete: showDelete ? deleteNavigation : undefined,
      showView: false,
      showEdit,
      showDelete,
    }));
  }

  return cols;
});

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Navigation list',
    href: slug.value,
  },
];

// Function to fetch data from server
const fetchNavigations = () => {
  const params: Record<string, any> = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page
  }
  
  if (searchQuery.value.trim()) {
    params.search_string = searchQuery.value.trim()
  }

  router.get(
    `/${slug.value}`,
    params,
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: [slug.value]
    }
  )
}

// Debounced data fetching for search query changes
const searchTimeout = ref<number | null>(null)
watch(
    searchQuery,
    (newQuery, oldQuery) => {
        // Only fetch if navigation has interacted (not on initial mount)
        if (!hasInitialized.value && oldQuery === undefined) return
        
        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value)
        }
        searchTimeout.value = window.setTimeout(() => {
            // Reset to first page when searching
            pagination.value.current_page = 1
            fetchNavigations()
        }, 500)
    },
    { immediate: false }
)

// Keep local pagination in sync when server returns new navigations payload
// Use a flag to prevent infinite loops when Datatable's watcher updates pagination
const isUpdatingFromServer = ref(false)
watch(
    navigations,
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
const isPaginationChange = ref(false)
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
        isPaginationChange.value = true
        
        fetchTimeout.value = window.setTimeout(() => {
            pagination.value.current_page = Number(currentPage) || 1
            pagination.value.per_page = Number(perPage) || 10
            
            // Make our request with search parameter
            // This will happen before Datatable's watcher can trigger
            fetchNavigations()
            
            // Reset flag after request is made
            setTimeout(() => {
                isPaginationChange.value = false
            }, 500)
        }, 50) // Shorter timeout to beat Datatable's watcher
    },
    { immediate: false }
)
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Navigation list" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                <Button v-if="canCreate" :onClick="createNavigation">Create</Button>
                <div class="relative w-full sm:w-64">
                    <label class="sr-only" for="navigation-search">Search navigations</label>
                    <input
                        id="navigation-search"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search name..."
                        class="border border-[var(--color-border-strong)] rounded-md text-sm bg-[var(--color-surface)] text-[var(--color-text)] focus:ring-2 focus:ring-opacity-50 focus:border-transparent w-full px-4 py-2 pr-8"
                        :style="{ '--tw-ring-color': 'var(--primary-color)' }"
                        @input="hasInitialized = true" />
                    <button
                        v-if="searchQuery"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-opacity-50"
                        :style="{ '--tw-ring-color': 'var(--primary-color)' }"
                        aria-label="Clear search"
                        @click="searchQuery = ''">
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <Datatable
                :data="navigations.data"
                :columns="columns"
                :pagination="pagination"
                :search-fields="[]"
                :enable-search="false"
                empty-message="No navigations found"
                empty-description="System navigations will appear here. Use search, pagination, or change rows per page to load data."
                export-file-name="navigations_list"
                @update:pagination="(newPagination: typeof pagination) => { hasInitialized = true; pagination = newPagination }">
            </Datatable>
        </div>
    </AppLayout>
</template>
