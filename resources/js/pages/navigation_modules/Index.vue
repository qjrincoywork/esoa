<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from '@/components/ui/button';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useNavigationModules } from '@/composables/navigationModules';
import { useModulePermissions } from '@/composables/useModulePermissions';

type Pagination = {
    current_page: number;
    per_page: number;
    total: number;
    data: unknown[];
};

const page = usePage();
const auth = computed(() => page.props.auth);
const user = computed(() => auth.value?.user);
const userDetail = computed(() => user.value?.user_detail);
const { slug, hasPermission, canCreate } = useModulePermissions();

const navigationModules = computed<Pagination>(() => {
    const data = (page.props as any).navigation_modules as Pagination | undefined;
    return data ?? { current_page: 1, per_page: 10, total: 0, data: [] };
});

const { createNavigationModule, editNavigationModule, deleteNavigationModule } = useNavigationModules();
const columnHelper = createColumnHelper<any>();

const pagination = ref({
    current_page: navigationModules.value.current_page,
    per_page:     Number(navigationModules.value.per_page),
    total:        navigationModules.value.total,
});

const searchQuery     = ref('');
const hasInitialized  = ref(false);
const isFirstLoad     = ref(true);

const baseColumns = [
    columnHelper.accessor('id', { header: 'ID' }),
    columnHelper.accessor('name', { header: 'Name' }),
    columnHelper.accessor('slug', { header: 'Slug' }),
    columnHelper.accessor((row: any) => row.navigation?.name ?? '—', {
        id: 'navigation',
        header: 'Navigation',
    }),
    columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ getValue }) => (getValue() === 1 ? 'Active' : 'Inactive'),
    }),
];

const handlerMap: Record<string, (item: any) => void> = {
    edit:    editNavigationModule,
    update:  editNavigationModule,
    delete:  deleteNavigationModule,
    destroy: deleteNavigationModule,
};

const columns = computed(() => {
    const subModules = (page.props.sub_modules as any[])
        .filter((m) => hasPermission(m.slug) && m.slug.split('.')[1] !== 'create')
        .map((m) => ({ ...m, handler: handlerMap[m.slug.split('.')[1]] }));

    return subModules.length
        ? [...baseColumns, createActionColumn(subModules)]
        : baseColumns;
});

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Navigation Modules', href: slug.value },
];

const fetchModules = () => {
    const params: Record<string, any> = {
        page:     pagination.value.current_page,
        per_page: pagination.value.per_page,
    };
    if (searchQuery.value.trim()) params.search_string = searchQuery.value.trim();

    router.get(`/${slug.value}`, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['navigation_modules'],
    });
};

// Debounced search
const searchTimeout = ref<number | null>(null);
watch(searchQuery, (newVal, oldVal) => {
    if (!hasInitialized.value && oldVal === undefined) return;
    if (searchTimeout.value) clearTimeout(searchTimeout.value);
    searchTimeout.value = window.setTimeout(() => {
        pagination.value.current_page = 1;
        fetchModules();
    }, 500);
}, { immediate: false });

// Sync pagination from server response
const isUpdatingFromServer = ref(false);
watch(navigationModules, (next) => {
    if (!next) return;
    isUpdatingFromServer.value = true;
    pagination.value.current_page = next.current_page;
    pagination.value.per_page     = Number(next.per_page);
    pagination.value.total        = next.total;

    if (isFirstLoad.value && next.total > 0) isFirstLoad.value = false;

    setTimeout(() => { isUpdatingFromServer.value = false; }, 300);
});

// Debounced pagination change
const fetchTimeout = ref<number | null>(null);
watch(
    () => [pagination.value.current_page, pagination.value.per_page],
    ([currentPage, perPage]) => {
        if (!hasInitialized.value || isUpdatingFromServer.value) return;
        if (fetchTimeout.value) clearTimeout(fetchTimeout.value);
        fetchTimeout.value = window.setTimeout(() => {
            pagination.value.current_page = Number(currentPage) || 1;
            pagination.value.per_page     = Number(perPage) || 10;
            fetchModules();
        }, 50);
    },
    { immediate: false }
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Navigation Modules" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                <Button v-if="canCreate || auth.is_superadmin" :onClick="createNavigationModule">Create</Button>
                <div class="relative w-full sm:w-64">
                    <label class="sr-only" for="nm-search">Search modules</label>
                    <input
                        id="nm-search"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search name or slug..."
                        class="border border-[var(--color-border-strong)] rounded-md text-sm bg-[var(--color-surface)] text-[var(--color-text)] focus:ring-2 focus:ring-opacity-50 focus:border-transparent w-full px-4 py-2 pr-8"
                        :style="{ '--tw-ring-color': 'var(--primary-color)' }"
                        @input="hasInitialized = true"
                    />
                    <button
                        v-if="searchQuery"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-opacity-50"
                        :style="{ '--tw-ring-color': 'var(--primary-color)' }"
                        aria-label="Clear search"
                        @click="searchQuery = ''"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <Datatable
                :data="navigationModules.data"
                :columns="columns"
                :pagination="pagination"
                :enable-search="false"
                empty-message="No navigation modules found"
                empty-description="Navigation modules will appear here. Use search, pagination, or change rows per page to load data."
                export-file-name="navigation_modules_list"
                @update:pagination="(p: typeof pagination) => { hasInitialized = true; pagination = p }"
            />
        </div>
    </AppLayout>
</template>
