<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import Datatable from '@/components/Datatable.vue';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useRoles } from '@/composables/roles';

type RolesPagination = {
    current_page: number
    per_page: number
    total: number
    data: unknown[]
}

const page = usePage();
const roles = computed(() => (page.props as any).roles as RolesPagination);
const { editRole, deleteRole } = useRoles();
const columnHelper = createColumnHelper();
const pagination = ref({
	current_page: roles.value.current_page,
	per_page: Number(roles.value.per_page),
	total: roles.value.total
})
const columns = [
  columnHelper.accessor('id', {
    header: 'ID',
  }),
  columnHelper.accessor('name', {
    header: 'Name',
  }),
  columnHelper.accessor('guard_name', {
    header: 'Guard Name',
  }),
  createActionColumn({
    basePath: '/roles',
    onEdit: editRole,
    onDelete: deleteRole,
  }),
]

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Role list',
        href: 'roles',
    },
];
// Debounced data fetching for pagination changes
const fetchTimeout = ref<number | null>(null)
watch(
    () => [pagination.value.current_page, pagination.value.per_page],
    ([currentPage, perPage], _prev) => {
        if (fetchTimeout.value) {
            clearTimeout(fetchTimeout.value)
        }
        fetchTimeout.value = window.setTimeout(() => {
            router.get(
                '/roles',
                {
                    page: Number(currentPage) || 1,
                    per_page: Number(perPage) || Number(roles.value.per_page)
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['roles']
                }
            )
        }, 200)
    },
    { immediate: false }
)
// Keep local pagination in sync when server returns new roles payload
watch(
    roles,
    (next) => {
        if (!next) return
        pagination.value.current_page = next.current_page
        pagination.value.per_page = Number(next.per_page)
        pagination.value.total = next.total
    }
)
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Role list" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <Datatable
                :data="roles.data"
                :columns="columns"
                :pagination="pagination"
                :search-fields="['name']"
                empty-message="No audit records found"
                empty-description="System roles will appear here"
                export-file-name="roles_list"
                @update:pagination="pagination = $event">
            </Datatable>
        </div>
    </AppLayout>
</template>
