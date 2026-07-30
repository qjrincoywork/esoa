/**
 * Chart primitives.
 *
 * Domain-free building blocks: pages map their data into the neutral shapes in `types.ts`
 * and compose these components. Adding a new chart form means adding a component here, not
 * widening an existing one.
 */
export { default as BarChart } from './BarChart.vue';
export { default as ChartCard } from './ChartCard.vue';
export { default as ChartLegend } from './ChartLegend.vue';
export { default as DonutChart } from './DonutChart.vue';
export { default as LineChart } from './LineChart.vue';
export { default as StatTile } from './StatTile.vue';

export {
    CURRENCY_SIGN,
    compactCurrency,
    compactNumber,
    formatNumber,
} from './format';
export {
    CHART_CHROME,
    DE_EMPHASIS_TONE,
    SERIES_TOKENS,
    toneVar,
} from './tokens';
export type {
    ChartDatum,
    ChartLegendItem,
    ChartSeriesDefinition,
    ChartSeriesPoint,
    ChartTableColumn,
    ChartTableRow,
} from './types';
