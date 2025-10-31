import { h } from 'vue';
import { router } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';

const columnHelper = createColumnHelper<any>()

export function createActionColumn(basePath: string) {
    return columnHelper.display({
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const item = row.original as { id?: number | string }

            return h('div', { class: 'flex gap-2 items-center' }, [
                // View Button
                h(
                    'button',
                    {
                        class: 'p-1 text-blue-600 hover:text-blue-800 transition-colors rounded',
                        title: 'View',
                        onClick: () => router.get(`${basePath}/${item.id}`)
                    },
                    [h(Eye, { size: 18 })]
                ),

                // Edit Button
                h(
                    'button',
                    {
                        class: 'p-1 text-yellow-600 hover:text-yellow-800 transition-colors rounded',
                        title: 'Edit',
                        onClick: () => router.get(`${basePath}/${item.id}/edit`)
                    },
                    [h(Pencil, { size: 18 })]
                ),

                // Delete Button
                h(
                    'button',
                    {
                        class: 'p-1 text-red-600 hover:text-red-800 transition-colors rounded',
                        title: 'Delete',
                        onClick: () => {
                            if (confirm('Are you sure you want to delete this item?')) {
                                router.delete(`${basePath}/${item.id}`, {
                                    preserveScroll: true
                                })
                            }
                        }
                    },
                    [h(Trash2, { size: 18 })]
                )
            ])
        }
    })
}


