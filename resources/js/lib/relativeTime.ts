const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 31_536_000],
  ['month', 2_592_000],
  ['week', 604_800],
  ['day', 86_400],
  ['hour', 3_600],
  ['minute', 60],
];

const relativeTime = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

/**
 * "3 days ago" — the time since an ISO 8601 timestamp, in the largest unit it amounts
 * to. Needs the machine-readable value the server sends beside its display label (e.g.
 * `last_login_at_value`), since the labels themselves are not reliably parseable.
 */
export const timeAgo = (iso: string): string => {
  const seconds = (Date.parse(iso) - Date.now()) / 1000;
  const [unit, size] = RELATIVE_UNITS.find(([, length]) => Math.abs(seconds) >= length) ?? RELATIVE_UNITS[RELATIVE_UNITS.length - 1];

  return relativeTime.format(Math.round(seconds / size), unit);
};
