/**
 * Number formatting for chart labels.
 *
 * Axis ticks and stat values are compacted (12.9K, 4.2M) so they never collide, while the
 * exact figure always stays reachable through the direct label, the tooltip and the table
 * twin — a compacted number is a convenience, never the only copy of a value.
 */

/** Currency sign of the application (mirrors config `vc.peso_sign`). */
export const CURRENCY_SIGN = '₱';

/**
 * Compact a number to at most one decimal with a magnitude suffix.
 * 986 -> "986", 12_940 -> "12.9K", 4_200_000 -> "4.2M".
 */
export function compactNumber(value: number): string {
    const abs = Math.abs(value);

    if (!Number.isFinite(value)) {
        return '0';
    }

    if (abs >= 1_000_000_000) {
        return trimZero(value / 1_000_000_000) + 'B';
    }
    if (abs >= 1_000_000) {
        return trimZero(value / 1_000_000) + 'M';
    }
    if (abs >= 1_000) {
        return trimZero(value / 1_000) + 'K';
    }

    return Math.round(value).toLocaleString();
}

/** Compact currency, e.g. "₱4.2M". */
export function compactCurrency(
    value: number,
    sign: string = CURRENCY_SIGN,
): string {
    const compacted = compactNumber(Math.abs(value));

    return (value < 0 ? '-' : '') + sign + compacted;
}

/** Full number with thousand separators, e.g. "1,284". */
export function formatNumber(value: number): string {
    return Math.round(value).toLocaleString();
}

/** Drop a trailing ".0" so "12.0K" reads as "12K". */
function trimZero(value: number): string {
    return value.toFixed(1).replace(/\.0$/, '');
}
