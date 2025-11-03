import { h } from 'vue';
import { router } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';

const columnHelper = createColumnHelper<any>()

export interface ActionColumnOptions {
    basePath: string;
    onView?: (item: any) => void;
    onEdit?: (item: any) => void;
    onDelete?: (item: any) => void;
    showView?: boolean;
    showEdit?: boolean;
    showDelete?: boolean;
}

export function createActionColumn(options: ActionColumnOptions | string) {
    // Support both old API (string basePath) and new API (options object)
    const opts: ActionColumnOptions = typeof options === 'string'
        ? { basePath: options }
        : options;

    const {
        basePath,
        onView,
        onEdit,
        onDelete,
        showView = true,
        showEdit = true,
        showDelete = true,
    } = opts;

    return columnHelper.display({
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const item = row.original as { id?: number | string };
            const actions = [];

            // View Button
            if (showView) {
                actions.push(
                    h(
                        'button',
                        {
                            class: 'p-1 text-green-600 hover:text-green-800 transition-colors rounded',
                            title: 'View',
                            onClick: () => {
                                if (onView) {
                                    onView(item);
                                } else {
                                    router.get(`${basePath}/${item.id}`);
                                }
                            },
                        },
                        [h(Eye, { size: 18 })]
                    )
                );
            }

            // Edit Button
            if (showEdit) {
                actions.push(
                    h(
                        'button',
                        {
                            class: 'p-1 text-blue-600 hover:text-blue-800 transition-colors rounded',
                            title: 'Edit',
                            onClick: () => {
                                if (onEdit) {
                                    onEdit(item);
                                } else {
                                    router.get(`${basePath}/${item.id}/edit`);
                                }
                            },
                        },
                        [h(Pencil, { size: 18 })]
                    )
                );
            }

            // Delete Button
            if (showDelete) {
                actions.push(
                    h(
                        'button',
                        {
                            class: 'p-1 text-red-600 hover:text-red-800 transition-colors rounded',
                            title: 'Delete',
                            onClick: () => {
                                if (onDelete) {
                                    onDelete(item);
                                } else {
                                    if (confirm('Are you sure you want to delete this item?')) {
                                        router.delete(`${basePath}/${item.id}`, {
                                            preserveScroll: true,
                                        });
                                    }
                                }
                            },
                        },
                        [h(Trash2, { size: 18 })]
                    )
                );
            }

            return h('div', { class: 'flex gap-2 items-center' }, actions);
        },
    });
}


