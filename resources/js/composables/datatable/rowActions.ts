import { h, type Component } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import RowActions from '@/components/RowActions.vue';

/** One action a row offers, already resolved for that row. */
export interface RowAction {
  key: string;
  label: string;
  /** A lucide icon component, or its name as a navigation module stores it ("Pencil"). */
  icon?: Component | string;
  /** Shown as an icon button beside the menu rather than inside it — for what a row is most often opened for. */
  inline?: boolean;
  /** Set apart at the foot of the menu, in red. */
  destructive?: boolean;
  onSelect: () => unknown;
}

/**
 * An actions column laid out by {@link RowActions}. `actionsFor` decides, per row, what
 * the row offers and how; the column only lays it out — so a table with many actions
 * stays one narrow column with every action named, rather than a strip of icons.
 */
export function createRowActionsColumn<T>(
  actionsFor: (row: T) => RowAction[],
  menuLabelFor?: (row: T) => string,
) {
  return createColumnHelper<T>().display({
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) => h(RowActions, {
      actions: actionsFor(row.original),
      menuLabel: menuLabelFor?.(row.original),
    }),
  });
}
