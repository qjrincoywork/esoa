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

type PermissionsPagination = {
    current_page: number
    per_page: number
    total: number
    data: unknown[]
}

const page = usePage();
const permissions = computed(() => (page.props as any).permissions as PermissionsPagination);
const { createPermission, editPermission, deletePermission } = usePermissions();
const columnHelper = createColumnHelper();
const pagination = ref({
	current_page: permissions.value.current_page,
	per_page: Number(permissions.value.per_page),
	total: permissions.value.total
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
    basePath: '/permissions',
    onEdit: editPermission,
    onDelete: deletePermission,
  }),
]

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Permission list',
        href: 'permissions',
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
                '/permissions',
                {
                    page: Number(currentPage) || 1,
                    per_page: Number(perPage) || Number(permissions.value.per_page)
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['permissions']
                }
            )
        }, 200)
    },
    { immediate: false }
)
// Keep local pagination in sync when server returns new permissions payload
watch(
    permissions,
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
        <Head title="Permission list" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <Button :onClick="createPermission">Create</Button>
            <Datatable
                :data="permissions.data"
                :columns="columns"
                :pagination="pagination"
                :search-fields="['name']"
                empty-message="No audit records found"
                empty-description="System permissions will appear here"
                export-file-name="permissions_list"
                @update:pagination="pagination = $event">
            </Datatable>
        </div>
    </AppLayout>
</template>
