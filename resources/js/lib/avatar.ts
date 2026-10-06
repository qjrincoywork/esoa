import { h, type Component, type VNode } from 'vue';
import { getInitials } from '@/composables/useInitials';

/** Initials, an avatar's worth, from a username too: "maria.santos" reads as MS, not M. */
export const initialsOf = (name?: string | null): string => getInitials((name ?? '').replace(/[._-]+/g, ' '));

/**
 * A round avatar for a table cell: the name's initials, or an icon when the actor is not
 * a person (a scheduled job, the system). Decorative — the name is always written beside it.
 */
export const avatar = (name?: string | null, icon?: Component): VNode => h(
  'span',
  {
    class: 'flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground',
    'aria-hidden': 'true',
  },
  icon ? h(icon, { class: 'size-4' }) : initialsOf(name),
);
