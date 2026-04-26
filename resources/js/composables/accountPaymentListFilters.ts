export const ACCOUNT_PAYMENT_LIST_FILTER_KEYS = [
  'deposit_date',
  'mode_of_payment',
  'created_by',
] as const

export type AccountPaymentListFilterKey = (typeof ACCOUNT_PAYMENT_LIST_FILTER_KEYS)[number]

export type AccountPaymentListFilters = Record<AccountPaymentListFilterKey, string>

export type AccountPaymentListOption = { value: string | number; name: string }

export function emptyAccountPaymentListFilters(): AccountPaymentListFilters {
  return {
    deposit_date: '',
    mode_of_payment: '',
    created_by: '',
  }
}

export function accountPaymentListFiltersToParams(filters: AccountPaymentListFilters): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  const t = (s: string) => s.trim()

  if (filters.deposit_date !== '') params.deposit_date = filters.deposit_date
  if (filters.mode_of_payment !== '') params.mode_of_payment = Number(filters.mode_of_payment)
  if (t(filters.created_by)) params.created_by = t(filters.created_by)

  return params
}

export function accountPaymentListFiltersActive(filters: AccountPaymentListFilters): boolean {
  return Object.values(filters).some((v) => String(v).trim() !== '')
}

export function accountPaymentListFiltersFromUrlQuery(url: string): AccountPaymentListFilters {
  const f = emptyAccountPaymentListFilters()
  const qs = url.includes('?') ? url.split('?')[1] : ''
  const sp = new URLSearchParams(qs)
  const get = (k: string) => sp.get(k)?.trim() ?? ''

  f.deposit_date = get('deposit_date')
  const modeOfPayment = sp.get('mode_of_payment')
  if (modeOfPayment !== null && modeOfPayment !== '') f.mode_of_payment = modeOfPayment
  f.created_by = get('created_by')

  return f
}
