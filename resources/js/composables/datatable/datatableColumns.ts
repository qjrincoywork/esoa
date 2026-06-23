import { h, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import Icon from '@/components/Icon.vue';
import { dispatchNotification } from '@/components/notification';
import { Auth, User, UserDetail, type BreadcrumbItem } from '@/types';

const columnHelper = createColumnHelper<any>()
const page = usePage();
const auth = computed(() => (page.props as any).auth as Auth);
const user = computed(() => auth.value?.user as User);
const userDetail = computed(() => user.value?.user_detail as UserDetail);

export interface ActionColumnOptions {
  customActions?: Array<{
    slug: string;
    name?: string;
    url?: string;
    icon?: any;
    color?: string;
    handler?: (item: any) => void;
    class?: string;
    dynamicProps?: (item: any) => Partial<{ name: string; icon: string | any; color: string }>;
  }>;
}

// Full static class strings so Tailwind's scanner includes every color at build time.
// Never build these dynamically with template literals — add new colors here instead.
const ACTION_COLOR_CLASSES: Record<string, string> = {
  // Reds / Pinks / Rose
  red:     'text-red-600    hover:text-red-800',
  rose:    'text-rose-500   hover:text-rose-700',
  pink:    'text-pink-500   hover:text-pink-700',
  // Oranges / Yellows / Ambers
  orange:  'text-orange-500 hover:text-orange-700',
  amber:   'text-amber-500  hover:text-amber-700',
  yellow:  'text-yellow-500 hover:text-yellow-700',
  // Greens / Limes / Emeralds / Teals
  lime:    'text-lime-500   hover:text-lime-700',
  green:   'text-green-600  hover:text-green-800',
  emerald: 'text-emerald-600 hover:text-emerald-800',
  teal:    'text-teal-600   hover:text-teal-800',
  // Blues / Cyans / Skys
  cyan:    'text-cyan-500   hover:text-cyan-700',
  sky:     'text-sky-500    hover:text-sky-700',
  blue:    'text-blue-600   hover:text-blue-800',
  // Purples / Violets / Indigos / Fuchsias
  indigo:  'text-indigo-600 hover:text-indigo-800',
  violet:  'text-violet-600 hover:text-violet-800',
  purple:  'text-purple-600 hover:text-purple-800',
  fuchsia: 'text-fuchsia-500 hover:text-fuchsia-700',
  // Neutrals / Special
  gray:    'text-gray-500   hover:text-gray-700',
  slate:   'text-slate-500  hover:text-slate-700',
  white:   'text-white      hover:text-gray-200',
};

export function createActionColumn(customActions: ActionColumnOptions['customActions']) {
  return columnHelper.display({
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) => {
      const item = row.original as {
        id?: number | string;
        status?: string;
        is_active?: number | boolean;
        deleted_at?: string | null;
        file_pdf?: string | null;
        attachment?: string | null;
        remittance_advice?: string | null;
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
          const resolved = action.dynamicProps ? { ...action, ...action.dynamicProps(item) } : action;
          const ActionIcon = resolved.icon || File;
          const label = resolved.name || resolved.slug || 'Action';
          const colorClass = ACTION_COLOR_CLASSES[resolved.color ?? ''] ?? ACTION_COLOR_CLASSES['gray'];
          const css = `cursor-pointer p-1 ${colorClass} transition-colors rounded`;

          if (
            (item.status?.toLowerCase() == 'paid' && action.slug == 'soas.edit')
            || (
              (userDetail.value?.employee_no == null || userDetail.value?.employee_no == '')
              && item.status?.toLowerCase() == 'endorsed'
              && action.slug == 'soas.edit'
            )
          ) {
            continue; // Skip actions for paid items
          }
          if ((item.file_pdf == '' || item.file_pdf == null) && action.slug === 'soas.billing_attachments') {
            continue; // Skip billing_attachments for paid items without PDF
          }
          if ((item.attachment == '' || item.attachment == null) && action.slug === 'concerns.preview_file') {
            continue; // Skip without attachment
          }
          if ((item.remittance_advice == '' || item.remittance_advice == null) && action.slug === 'account_payments.preview_file') {
            continue; // Skip without remittance_advice
          }
          if (item.status?.toLowerCase() == 'closed' && action.slug === 'concerns.edit') {
            continue; // Skip actions for closed items
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
              typeof resolved.icon === 'string'
                ? h(Icon, { name: resolved.icon, size: 18 })
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


