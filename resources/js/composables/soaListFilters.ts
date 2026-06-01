/**
 * SOA list filter state aligned with SavingSoaForm fields (account type → account → branch → billing ref, SOA number, status).
 */
export const SOA_LIST_FILTER_KEYS = [
  'account_type',
  'account_code',
  'branch_code',
  'billing_ref',
  'soanum',
  'status',
  'due_in',
  'due_date_from',
  'due_date_to',
  'bill_date_from',
  'bill_date_to',
] as const
export type SoaListFilterKey = (typeof SOA_LIST_FILTER_KEYS)[number]

export type SoaListFilters = Record<SoaListFilterKey, string>

export type SoaListOption = { value: string | number; name: string }

export function emptySoaListFilters(): SoaListFilters {
  return {
    account_type: '',
    account_code: '',
    branch_code: '',
    billing_ref: '',
    soanum: '',
    status: '',
    due_in: '',
    due_date_from: '',
    due_date_to: '',
    bill_date_from: '',
    bill_date_to: '',
  }
}

/** Non-empty filter values as GET params (aligned with ListRequest). */
export function soaListFiltersToParams(filters: SoaListFilters): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  const t = (s: string) => s.trim()
  if (t(filters.account_type)) params.account_type = t(filters.account_type)
  if (t(filters.account_code)) params.account_code = t(filters.account_code)
  if (t(filters.branch_code)) params.branch_code = t(filters.branch_code)
  if (t(filters.billing_ref)) params.billing_ref = t(filters.billing_ref)
  if (t(filters.soanum)) params.soanum = t(filters.soanum)
  if (filters.status !== '') params.status = Number(filters.status)
  if (filters.due_in !== '') params.due_in = Number(filters.due_in)
  if (t(filters.due_date_from)) params.due_date_from = t(filters.due_date_from)
  if (t(filters.due_date_to)) params.due_date_to = t(filters.due_date_to)
  if (t(filters.bill_date_from)) params.bill_date_from = t(filters.bill_date_from)
  if (t(filters.bill_date_to)) params.bill_date_to = t(filters.bill_date_to)
  return params
}

export function soaListFiltersActive(filters: SoaListFilters): boolean {
  return Object.values(filters).some((v) => String(v).trim() !== '')
}

/** Restore filter inputs from the current URL query (e.g. shared links, reload). */
export function soaListFiltersFromUrlQuery(url: string): SoaListFilters {
  const f = emptySoaListFilters()
  const qs = url.includes('?') ? url.split('?')[1] : ''
  const sp = new URLSearchParams(qs)
  const get = (k: string) => sp.get(k)?.trim() ?? ''
  f.account_type = get('account_type')
  f.account_code = get('account_code')
  f.branch_code = get('branch_code')
  f.billing_ref = get('billing_ref')
  f.soanum = get('soanum')
  f.due_in = get('due_in')
  f.due_date_from = get('due_date_from')
  f.due_date_to = get('due_date_to')
  f.bill_date_from = get('bill_date_from')
  f.bill_date_to = get('bill_date_to')
  const st = sp.get('status')
  if (st !== null && st !== '') f.status = st
  return f
}
  