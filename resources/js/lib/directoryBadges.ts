import { h, type VNode } from 'vue';

/**
 * A small pill, so a row's class reads at a glance down a column.
 *
 * Shared by the unmapped-accounts listing and its directory pane so both render the
 * same badge for the same fact rather than keeping two copies in sync by hand.
 */
export const badge = (text: string, classes: string): VNode =>
  h('span', { class: ['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', classes] }, text);

/** Who, if anyone, already has a row — an account/branch code mapped to zero or more users. */
export const mappedStatusBadge = (users: unknown): VNode => {
  const names = Array.isArray(users) ? (users as string[]) : [];

  if (!names.length) {
    return badge('Unmapped', 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400');
  }

  const shown = names.length > 2 ? `${names.slice(0, 2).join(', ')} +${names.length - 2}` : names.join(', ');

  return h(
    'span',
    { title: names.join(', ') },
    [badge(`Mapped: ${shown}`, 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300')],
  );
};
