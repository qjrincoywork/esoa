import type { VizTone } from '@/types';

/**
 * Resolution of a semantic tone to the themed custom property that paints it.
 *
 * Components never hard-code a hex: they ask for a tone, the theme decides the step. That
 * is what lets the same chart render correctly in the light, dark, warm and dim themes.
 */
export const toneVar = (tone: VizTone | null | undefined = 'neutral'): string =>
    `var(--viz-${tone ?? 'neutral'})`;

/** Chart chrome, taken from the design system so charts sit inside the active theme. */
export const CHART_CHROME = {
    grid: 'var(--viz-grid)',
    axis: 'var(--viz-axis)',
    surface: 'var(--viz-surface)',
} as const;

/**
 * Categorical slots in their fixed order.
 *
 * Assign in sequence and never cycle: a 9th series is folded into "Other" or faceted
 * instead, because a generated hue is indistinguishable from an existing one under
 * color-vision deficiency.
 */
export const SERIES_TOKENS: VizTone[] = ['series-1', 'series-2', 'series-3'];

/** Tone used for marks that are context rather than the point of the chart. */
export const DE_EMPHASIS_TONE: VizTone = 'muted';
