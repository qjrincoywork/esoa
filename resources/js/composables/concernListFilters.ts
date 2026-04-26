/**
 * Concern list filter state aligned with SavingConcernForm fields.
 */
export const CONCERN_LIST_FILTER_KEYS = [
  'title',
  'description',
  'type',
  'status',
] as const
export type ConcernListFilterKey = (typeof CONCERN_LIST_FILTER_KEYS)[number]

export type ConcernListFilters = Record<ConcernListFilterKey, string>

export type ConcernListOption = { value: string | number; name: string }

export function emptyConcernListFilters(): ConcernListFilters {
  return {
    title: '',
    description: '',
    type: '',
    status: '',
  }
}

/** Non-empty filter values as GET params (aligned with ListRequest). */
export function concernListFiltersToParams(filters: ConcernListFilters): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  const t = (s: string) => s.trim()
  if (t(filters.title)) params.title = t(filters.title)
  if (t(filters.description)) params.description = t(filters.description)
  if (filters.type !== '') params.type = Number(filters.type)
  if (filters.status !== '') params.status = Number(filters.status)
  return params
}

export function concernListFiltersActive(filters: ConcernListFilters): boolean {
  return Object.values(filters).some((v) => String(v).trim() !== '')
}

/** Restore filter inputs from the current URL query (e.g. shared links, reload). */
export function concernListFiltersFromUrlQuery(url: string): ConcernListFilters {
  const f = emptyConcernListFilters()
  const qs = url.includes('?') ? url.split('?')[1] : ''
  const sp = new URLSearchParams(qs)
  const get = (k: string) => sp.get(k)?.trim() ?? ''
  f.title = get('title')
  f.description = get('description')
  const st = sp.get('status')
  if (st !== null && st !== '') f.status = st
  const t = sp.get('type')
  if (t !== null && t !== '') f.type = t
  return f
}
