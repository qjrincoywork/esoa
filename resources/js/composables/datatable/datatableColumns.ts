import { h } from 'vue';
import { router } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import Icon from '@/components/Icon.vue';
import { dispatchNotification } from '@/components/notification';

const columnHelper = createColumnHelper<any>()

export interface ActionColumnOptions {
  customActions?: Array<{
    slug: string;
    name?: string;
    url?: string;
    icon?: any;
    color?: string;
    handler?: (item: any) => void;
    class?: string;
  }>;
}

export function createActionColumn(customActions: ActionColumnOptions) {
  return columnHelper.display({
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) => {
      const item = row.original as {
        id?: number | string
        status?: string;
        file_pdf?: string | null;
      };
      const actions = [];

      // Utility to wrap a button with tooltip
      const withTooltip = (buttonVNode: any, tooltipText: string) => {
        return h(
          Tooltip,
          null,
          {
            default: () => [
              h(TooltipTrigger, null, () => [buttonVNode]),
              h(TooltipContent, null, () => tooltipText)
            ]
          }
        );
      };

      // Render dynamic custom actions
      if (Array.isArray(customActions) && customActions.length > 0) {
        for (const action of customActions) {
          const ActionIcon = action.icon || File;
          const label = action.name || action.slug || 'Action';
          const css = `cursor-pointer p-1 text-${action.color}-600 hover:text-${action.color}-800 transition-colors rounded`;

          if (item.status?.toLowerCase() == 'paid' && action.slug === 'soas.edit') {
            continue; // Skip actions for paid items
          }
          if ((item.file_pdf == '' || item.file_pdf == null) && action.slug === 'soas.billing_attachments') {
            continue; // Skip billing_attachments for paid items without PDF
          }
          const button = h(
            'button',
            {
              type: 'button',
              class: css,
              onClick: () => {
                if (action.handler) {
                  action.handler(item);
                } else if (action.url) {
                  router.get(action.url);
                }
              },
            },
            [
              typeof action.icon === 'string'
                ? h(Icon, { name: action.icon, size: 18 })
                : h(ActionIcon, { size: 18 })
            ]
          );
          actions.push(withTooltip(button, label));
        }
      }

      return h('div', { class: 'flex gap-2 items-center' }, actions);
    },
  });
}


