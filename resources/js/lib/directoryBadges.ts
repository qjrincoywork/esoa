import { h, type VNode } from 'vue';
import type { AccountStanding } from '@/composables/unmappedAccounts';

/**
 * A small pill, so a row's class reads at a glance down a column.
 *
 * Shared by the unmapped-accounts listing and its directory pane so both render the
 * same badge for the same fact rather than keeping two copies in sync by hand.
 */
export const badge = (text: string, classes: string): VNode =>
  h('span', { class: ['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', classes] }, text);

/**
 * The text of an account standing, as sent by the server (`App\Enums\AccountStanding::present()`)
 * — the rule, labels and colors all live there. `prefix` says whose standing it is when it is not the row's
 * own, e.g. "Account" reads "Account expired" on a branch.
 */
export const standingText = (standing?: AccountStanding | null, prefix = ''): string => {
  const label = standing?.label ?? '—';

  return prefix && standing ? `${prefix} ${label.toLowerCase()}` : label;
};

export const standingBadge = (standing?: AccountStanding | null, prefix = ''): VNode =>
  badge(standingText(standing, prefix), standing?.color ?? '');

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
