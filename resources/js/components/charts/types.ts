import type { VizTone } from '@/types';

/**
 * Shared shapes for the chart primitives.
 *
 * The chart components never import a domain type (SOA, user, …): a page maps its data
 * into these neutral shapes, which keeps the charts reusable across modules and keeps the
 * domain logic in the page/composable where it belongs.
 */

/** One mark of a categorical chart: a bar, a slice, a ranking row. */
export interface ChartDatum {
    /** Stable identity — colors and hover state follow the key, never the row index. */
    key: string;
    label: string;
    value: number;
    /** Pre-formatted value shown as the direct label (falls back to the raw value). */
    valueLabel?: string;
    /** Optional second line of context (e.g. "12 invoices"). */
    secondaryLabel?: string;
    /** Semantic tone; omitted marks inherit the chart's default series tone. */
    tone?: VizTone;
    /** When set, the mark becomes a link to the filtered list. */
    href?: string;
    /** Emphasis marks wear the accent tone, the rest the de-emphasis gray. */
    emphasis?: boolean;
}

/** A legend key. Bars/areas use a rect, lines a short stroke — mirroring the mark. */
export interface ChartLegendItem {
    key: string;
    label: string;
    tone: VizTone;
    shape?: 'rect' | 'line';
    value?: string;
}

/** Column of a chart's table twin. */
export interface ChartTableColumn {
    key: string;
    label: string;
    align?: 'left' | 'right';
}

/** Row of a chart's table twin; cells are keyed by column. */
export interface ChartTableRow {
    key: string;
    cells: Record<string, string | number>;
}

/**
 * A point of a time series: the period identity the chart needs, plus whatever numeric
 * fields the series definitions name. Only `key` and `label` are part of the contract, so
 * any domain row carrying them can be plotted as-is.
 */
export interface ChartSeriesPoint {
    key: string;
    label: string;
}

/** Definition of one line/area in a time series. */
export interface ChartSeriesDefinition {
    key: string;
    label: string;
    token: VizTone;
    /** Optional pre-formatted values keyed by point key, used by the tooltip. */
    formattedField?: string;
}
