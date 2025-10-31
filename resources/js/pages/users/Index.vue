<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import Datatable from '@/components/Datatable.vue';
import { createActionColumn } from '@/composables/datatable/datatableColumns';


type UsersPagination = {
    current_page: number
    per_page: number
    total: number
    data: unknown[]
}

const page = usePage();
const user = (page.props as any).auth.user as unknown;
const users = computed(() => (page.props as any).users as UsersPagination);

const columnHelper = createColumnHelper();
const pagination = ref({
	current_page: users.value.current_page,
	per_page: Number(users.value.per_page),
	total: users.value.total
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
  createActionColumn('/users'),
]

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'User list',
        href: 'users',
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
                '/users',
                {
                    page: Number(currentPage) || 1,
                    per_page: Number(perPage) || Number(users.value.per_page)
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['users']
                }
            )
        }, 200)
    },
    { immediate: false }
)
// Keep local pagination in sync when server returns new users payload
watch(
    users,
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
        <Head title="User list" />
        <div class="bg-[var(--color-surface)] rounded-xl shadow-sm border border-[var(--color-border)] p-6">
            <Datatable
                :data="users.data"
                :columns="columns"
                :pagination="pagination"
                :search-fields="['username', 'email']"
                empty-message="No audit records found"
                empty-description="System activities will appear here"
                export-file-name="activity_log"
                @update:pagination="pagination = $event">
            </Datatable>
        </div>
    </AppLayout>
</template>
