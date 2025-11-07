import { h } from 'vue';
import { router } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { dispatchNotification } from '@/components/notification';

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

            // Utility to wrap a button with tooltip
            const withTooltip = (buttonVNode: any, tooltipText: string) => {
                return h(
                    Tooltip,
                    null,
                    {
                        default: () => [
                            h(
                                TooltipTrigger,
                                null,
                                () => [buttonVNode]
                            ),
                            h(TooltipContent, null, () => tooltipText)
                        ]
                    }
                );
            };

            // View Button
            if (showView) {
                const button = h(
                    'button',
                    {
                        type: 'button',
                        class: 'p-1 text-green-600 hover:text-green-800 transition-colors rounded',
                        onClick: () => {
                            if (onView) {
                                onView(item);
                            } else {
                                router.get(`${basePath}/${item.id}`);
                            }
                        },
                    },
                    [h(Eye, { size: 18 })]
                );
                actions.push(withTooltip(button, 'View'));
            }

            // Edit Button
            if (showEdit) {
                const button = h(
                    'button',
                    {
                        type: 'button',
                        class: 'p-1 text-blue-600 hover:text-blue-800 transition-colors rounded',
                        onClick: () => {
                            if (onEdit) {
                                onEdit(item);
                            } else {
                                router.get(`${basePath}/${item.id}/edit`);
                            }
                        },
                    },
                    [h(Pencil, { size: 18 })]
                );
                actions.push(withTooltip(button, 'Edit'));
            }

            // Delete Button
            if (showDelete) {
                const button = h(
                    'button',
                    {
                        type: 'button',
                        class: 'p-1 text-red-600 hover:text-red-800 transition-colors rounded',
                        onClick: () => {
                            if (onDelete) {
                                onDelete(item);
                            } else {
                                dispatchNotification({ title: 'Warning!', content: 'Successfully Deleted.', type: 'warning' });
                                // if (confirm('Are you sure you want to delete this item?')) {
                                //     router.delete(`${basePath}/${item.id}`, {
                                //         preserveScroll: true,
                                //     });
                                // }
                            }
                        },
                    },
                    [h(Trash2, { size: 18 })]
                );
                actions.push(withTooltip(button, 'Delete'));
            }

            return h('div', { class: 'flex gap-2 items-center' }, actions);
        },
    });
}


