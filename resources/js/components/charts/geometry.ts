/**
 * Pure geometry helpers for the SVG charts.
 *
 * Kept free of Vue and of any DOM access so the scales and paths can be unit tested and
 * reasoned about on their own — the components stay declarative rendering only.
 */

export interface Point {
    x: number;
    y: number;
}

/**
 * Round an axis maximum up to a clean number and return evenly spaced ticks from 0.
 *
 * Ticks carry the values that are not directly labelled, so they must read as round
 * numbers (0 / 1,000 / 2,000), never as the raw data maximum.
 */
export function niceTicks(max: number, tickCount = 4): number[] {
    if (!Number.isFinite(max) || max <= 0) {
        return [0, 1];
    }

    const rawStep = max / tickCount;
    const magnitude = 10 ** Math.floor(Math.log10(rawStep));
    const normalized = rawStep / magnitude;
    const niceStep =
        (normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10) *
        magnitude;
    const niceMax = Math.ceil(max / niceStep) * niceStep;

    const ticks: number[] = [];
    for (let value = 0; value <= niceMax + niceStep / 2; value += niceStep) {
        ticks.push(Number(value.toFixed(10)));
    }

    return ticks;
}

/** Straight-segment path through the given points ("M x y L x y …"). */
export function buildLinePath(points: Point[]): string {
    if (points.length === 0) {
        return '';
    }

    return points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'}${round(point.x)},${round(point.y)}`,
        )
        .join(' ');
}

/** Closed path under a line, used for the single-series area wash. */
export function buildAreaPath(points: Point[], baselineY: number): string {
    if (points.length === 0) {
        return '';
    }

    const first = points[0];
    const last = points[points.length - 1];

    return `${buildLinePath(points)} L${round(last.x)},${round(baselineY)} L${round(first.x)},${round(baselineY)} Z`;
}

/**
 * Evenly spaced x positions for `count` points inside [left, right].
 * A single point is centered rather than pinned to the left edge.
 */
export function spreadX(count: number, left: number, right: number): number[] {
    if (count <= 0) {
        return [];
    }
    if (count === 1) {
        return [(left + right) / 2];
    }

    const step = (right - left) / (count - 1);

    return Array.from({ length: count }, (_, index) => left + step * index);
}

/**
 * Pick at most `max` label indexes, always keeping the first and the last, so x-axis
 * labels never overlap on a long series.
 */
export function labelIndexes(count: number, max: number): number[] {
    if (count <= max) {
        return Array.from({ length: count }, (_, index) => index);
    }

    const step = Math.ceil(count / max);
    const indexes: number[] = [];

    for (let index = 0; index < count; index += step) {
        indexes.push(index);
    }

    if (indexes[indexes.length - 1] !== count - 1) {
        indexes.push(count - 1);
    }

    return indexes;
}

/**
 * Donut arc description for each value: the dash length and offset on a stroked circle.
 *
 * A gap the width of the surface stroke separates touching segments — the separation is
 * negative space, never a border drawn around the mark.
 */
export function donutSegments(
    values: number[],
    circumference: number,
    gap = 2,
): Array<{ dash: number; offset: number }> {
    const total = values.reduce((sum, value) => sum + Math.max(value, 0), 0);

    if (total <= 0) {
        return values.map(() => ({ dash: 0, offset: 0 }));
    }

    const drawable = values.filter((value) => value > 0).length;
    const totalGap = drawable > 1 ? gap * drawable : 0;
    let cursor = 0;

    return values.map((value) => {
        if (value <= 0) {
            return { dash: 0, offset: -cursor };
        }

        const length = Math.max(
            (value / total) * (circumference - totalGap),
            0.5,
        );
        const segment = { dash: length, offset: -cursor };
        cursor += length + (drawable > 1 ? gap : 0);

        return segment;
    });
}

/** Two-decimal rounding, keeping SVG path strings short. */
function round(value: number): number {
    return Math.round(value * 100) / 100;
}
