<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch, computed, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useRoles } from '@/composables/roles';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { Key } from 'lucide-vue-next';

type RolesPagination = {
    current_page: number
    per_page: number
    total: number
    data: unknown[]
}

const page = usePage();
const { canCreate, slug, hasPermission } = useModulePermissions();
const roles = computed(() => (page.props as any).roles as RolesPagination);
const allPermissions = computed(() => (page.props as any).permissions);
const { createRole, editRole, deleteRole, manageRolePermissions } = useRoles();
const columnHelper = createColumnHelper();
const pagination = ref({
	current_page: roles.value.current_page,
	per_page: Number(roles.value.per_page),
	total: roles.value.total
})
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
  edit: editRole,
  update: editRole,
  delete: deleteRole,
  destroy: deleteRole,
}

const columns = computed(() => {
  const subModules = page.props.sub_modules
    .filter((m: any) => hasPermission(m.slug) && m.slug.split('.')[1] !== 'create')
    .map((m: any) => ({
      ...m,
      handler: handlerMap[m.slug.split('.')[1]],
    }))

  const customActions = [
    {
      slug: 'manage_permissions',
      name: 'Manage Permissions',
      icon: Key,
      color: 'blue',
      handler: (role: any) => manageRolePermissions(role, allPermissions.value),
    },
    ...subModules,
  ]

  return [...baseColumns, createActionColumn(customActions)]
})

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
            <Button :onClick="createRole">Create</Button>
            <Datatable
                :data="roles.data"
                :columns="columns"
                :pagination="pagination"
                :show-selection-column="true"
                :search-fields="['name']"
                empty-message="No audit records found"
                empty-description="System roles will appear here"
                export-file-name="roles_list"
                @update:pagination="pagination = $event">
            </Datatable>
        </div>
    </AppLayout>
</template>
