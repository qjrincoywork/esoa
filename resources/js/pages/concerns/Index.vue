<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { Input } from '@/components/ui/input';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { useConcerns } from '@/composables/concerns';

const page = usePage();
const { canCreate, canEdit, canDelete, hasPermission } = useModulePermissions();
const { newConcern, editConcern, deleteConcern } = useConcerns();

const concerns = computed(() => (page.props as any).concerns);

const columnHelper = createColumnHelper();

const columns = [
  columnHelper.accessor('title', {
    header: 'Title',
    cell: (info) => info.getValue(),
  }),
  columnHelper.accessor('type', {
    header: 'Type',
    cell: (info) => info.getValue(),
  }),
  columnHelper.accessor('status', {
    header: 'Status',
    cell: (info) => info.getValue(),
  }),
  columnHelper.accessor('user.name', {
    header: 'User',
    cell: (info) => info.getValue(),
  }),
  createActionColumn({
    canEdit,
    canDelete,
    onEdit: (row) => editConcern(row),
    onDelete: (row) => deleteConcern(row),
  }),
];

const search = ref('');

function handleSearch() {
  router.get('/concerns', { search: search.value }, { preserveState: true });
}
</script>

<template>
  <AppLayout title="Concerns">
    <Head title="Concern list" />

    <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
      <div class="flex flex-col gap-4 mb-4">
        <div class="flex flex-row lg:flex-row justify-between items-stretch lg:items-start gap-4">
          <div class="flex flex-1 flex-row gap-3 min-w-0">
            <Button class="cursor-pointer" v-if="canCreate" :onClick="newConcern">Create</Button>
          </div>
        </div>
      </div>
      <Datatable :data="concerns.data" :columns="columns" :pagination="concerns" />
    </div>
  </AppLayout>
</template>
