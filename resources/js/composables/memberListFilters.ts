/**
 * Member list filter state
 */
export const MEMBER_LIST_FILTER_KEYS = [
  'policynum',
  'lastname',
  'firstname',
] as const
export type MemberListFilterKey = (typeof MEMBER_LIST_FILTER_KEYS)[number]

export type MemberListFilters = Record<MemberListFilterKey, string>

export type MemberListOption = { value: string | number; name: string }

export function emptyMemberListFilters(): MemberListFilters {
  return {
    policynum: '',
    lastname: '',
    firstname: '',
  }
}

/** Non-empty filter values as GET params (aligned with ListRequest). */
export function memberListFiltersToParams(filters: MemberListFilters): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  const t = (s: string) => s.trim()
  if (t(filters.policynum)) params.policynum = t(filters.policynum)
  if (t(filters.lastname)) params.lastname = t(filters.lastname)
  if (t(filters.firstname)) params.firstname = t(filters.firstname)
  return params
}

export function memberListFiltersActive(filters: MemberListFilters): boolean {
  return Object.values(filters).some((v) => String(v).trim() !== '')
}

/** Restore filter inputs from the current URL query (e.g. shared links, reload). */
export function memberListFiltersFromUrlQuery(url: string): MemberListFilters {
  const f = emptyMemberListFilters()
  const qs = url.includes('?') ? url.split('?')[1] : ''
  const sp = new URLSearchParams(qs)
  const get = (k: string) => sp.get(k)?.trim() ?? ''
  f.policynum = get('policynum')
  f.lastname = get('lastname')
  f.firstname = get('firstname')
  return f
}
  