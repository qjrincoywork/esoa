<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import Datatable from '@/Components/Datatable.vue';


const page = usePage();
const user = page.props.auth.user;
const users = page.props.users;

const columnHelper = createColumnHelper()

const pagination = ref({
    current_page: users.current_page,
    per_page: Number(users.per_page),
    total: users.total
})
const columns = [
  columnHelper.accessor('id', {
    header: 'ID',
  }),
  columnHelper.accessor('username', {
    header: 'Username',
  }),
  columnHelper.accessor('email', {
    header: 'Email',
  }),
]

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'User list',
        href: 'users',
    },
];
watch(
    pagination,
    newPagination => {
        router.get('/users',
            {
                page: newPagination.current_page,
                per_page: Number(newPagination.per_page)
            },
            {
                preserveState: true,
                preserveScroll: true,
                // onFinish: () => (loading.value = false)
            }
        )
  },
  { deep: true }
)
// watch(
//     pagination,
//     newPagination => {
//         // loading.value = true
//         router.get(
//             router('users.index'),
//             {
//                 page: newPagination.current_page,
//                 per_page: Number(newPagination.per_page)
//             },
//             {
//                 preserveState: true,
//                 preserveScroll: true,
//                 // onFinish: () => (loading.value = false)
//             }
//         )
//     },
//     { deep: true }
// )
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="User list" />
        <div class="bg-[var(--color-surface)] rounded-xl shadow-sm border border-[var(--color-border)] p-6">
            <Datatable
                :data="users.data"
                :columns="columns"
                :pagination="pagination"
                :search-fields="['user.name', 'event', 'auditable_type', 'created_at']"
                empty-message="No audit records found"
                empty-description="System activities will appear here"
                export-file-name="activity_log"
                @update:pagination="pagination = $event">
            </Datatable>
        </div>
    </AppLayout>
</template>
