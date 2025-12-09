import { h } from 'vue';
import { router } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { Eye, Pencil, Trash2, Recycle } from 'lucide-vue-next';
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
                router.get(`${basePath}/${item.id}/show`);
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
        const deleteOrRestore = item.deleted_at ? 'Restore' : 'Delete'
        const icon = item.deleted_at ? Recycle : Trash2
        const color = item.deleted_at ? 'green' : 'red'
        const button = h(
          'button',
          {
            type: 'button',
            class: `p-1 text-${color}-600 hover:text-${color}-800 transition-colors rounded`,
            onClick: async () => {
              if (typeof onDelete !== 'function') {
                // Fallback to a default delete visit if no handler provided
                try {
                  await router.delete(`${basePath}/${item.id}`);
                } catch (error) {
                  dispatchNotification({ title: 'Warning!', content: 'Encountered internal Server Error:' + String(error), type: 'warning' });
                }
                return;
              }

              try {
                // Support async handler
                await Promise.resolve(onDelete(item));
              } catch (error) {
                dispatchNotification({ title: 'Warning!', content: 'Encountered internal Server Error:' + String(error), type: 'warning' });
              }
            },
          },
          [h(icon, { size: 18 })]
        );
        actions.push(withTooltip(button, deleteOrRestore));
      }

      return h('div', { class: 'flex gap-2 items-center' }, actions);
    },
  });
}


