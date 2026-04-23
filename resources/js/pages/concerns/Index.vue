<script setup lang="ts">
import { ref, computed, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import { Button } from "@/components/ui/button";
import { Input } from '@/components/ui/input';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { useConcerns } from '@/composables/concerns';
import RightPane from '@/components/RightPane.vue';

const page = usePage();
const { canCreate, slug, hasPermission } = useModulePermissions();
const {
  newConcern,
  viewConcern,
  editConcern,
  deleteConcern,
  openPane,
  closePane,
  rightPaneVisible,
  rightPaneTitle,
  rightPaneLoading,
  rightPaneError,
  rightPaneContentComponent,
  rightPaneComponentProps,
 } = useConcerns();

const concerns = computed(() => (page.props as any).concerns);

const columnHelper = createColumnHelper();

const baseColumns: any[] = [
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

    cell: ({ row, getValue }) => {
      const r = row.original as { status?: string; status_color?: string }
      return h(
        'span',
        {
          class: [
            r.status_color ?? '',
            'px-2 py-1 rounded-md font-medium',
          ],
        },
        String(getValue() ?? ''),
      )
    },
  }),
  columnHelper.accessor('created_by', {
    header: 'Created By',
    cell: (info) => info.getValue(),
  }),
];
const handlerMap: Record<string, Function> = {
  edit: editConcern,
  update: editConcern,
  delete: deleteConcern,
  destroy: deleteConcern,
}

const columns = computed(() => {
  const rawModules = (page.props as { sub_modules?: { slug: string }[] }).sub_modules ?? []
  const subModules = rawModules
    .filter((m: { slug: string }) => hasPermission(m.slug)
      && m.slug.split('.')[1] != 'create'
      && m.slug.split('.')[1] != 'file_list'
    )
    .map((m: { slug: string }) => ({
      ...m,
      handler: handlerMap[m.slug.split('.')[1]],
    }))

  return subModules.length
    ? [...baseColumns, createActionColumn(subModules)]
    : baseColumns
})

const search = ref('');

function handleSearch() {
  router.get('/concerns', { search: search.value }, { preserveState: true });
}

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Concern list',
    href: slug.value,
  },
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Concern list" />

    <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
      <div class="flex flex-col gap-4 mb-4">
        <div class="flex flex-row lg:flex-row justify-between items-stretch lg:items-start gap-4">
          <div class="flex flex-1 flex-row gap-3 min-w-0">
            <Button class="cursor-pointer" v-if="canCreate" :onClick="newConcern">Create</Button>
          </div>
        </div>
      </div>
      <Datatable
        :data="concerns.data"
        :enable-row-click="true"
        :row-click="viewConcern"
        :columns="columns"
        :pagination="concerns"
      />
    </div>
    <RightPane
      :open="rightPaneVisible"
      :title="rightPaneTitle"
      :loading="rightPaneLoading"
      :error="rightPaneError"
      :content-component="rightPaneContentComponent"
      :component-props="rightPaneComponentProps"
      @update:open="(v) => { if (!v && !rightPaneLoading) closePane('right') }"
    />
  </AppLayout>
</template>
