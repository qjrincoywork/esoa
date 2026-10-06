import { computed, ref, watch } from 'vue'
import type { SearchableComboboxItem } from '@/components/ui/searchable-combobox'
import { debounce } from '@/composables/utilities/helper'

/** Paginated lookup payload as returned by the `get_accounts` / `get_branches` endpoints. */
export type LookupPage = {
  data?: SearchableComboboxItem[]
  current_page?: number
  last_page?: number
}

export type LookupParams = Record<string, string | number | null | undefined>

/**
 * State for a server-backed, searchable, paginated SearchableCombobox.
 *
 * - `params()` supplies the request's extra params; empty values are dropped so the
 *   query string never carries a literal "undefined".
 * - `enabled()` gates loading; when it is false the options are simply cleared.
 * - Typing in `search` reloads page 1 (debounced); `loadMore()` appends the next page.
 * - Only the latest request may write state, so a slow reply to an older search can
 *   never overwrite the results of a newer one.
 */
export function useLookupOptions(
  fetcher: (params: Record<string, string | number>) => Promise<LookupPage | undefined>,
  params: () => LookupParams,
  enabled: () => boolean = () => true,
  debounceMs = 400,
) {
  const items = ref<SearchableComboboxItem[]>([])
  const search = ref('')
  const page = ref(1)
  const lastPage = ref(1)
  const loadingMore = ref(false)
  const hasMore = computed(() => page.value < lastPage.value)

  let latestRequest = 0

  const reset = () => {
    latestRequest++
    items.value = []
    page.value = 1
    lastPage.value = 1
    loadingMore.value = false
  }

  const load = async (pageNum = 1) => {
    if (!enabled()) {
      reset()
      return
    }

    const requestId = ++latestRequest
    const append = pageNum > 1
    loadingMore.value = append

    const query: Record<string, string | number> = { page: pageNum }
    const name = search.value.trim()
    if (name) query.name = name
    for (const [key, value] of Object.entries(params())) {
      if (value !== null && value !== undefined && value !== '') query[key] = value
    }

    try {
      const result = await fetcher(query)
      if (requestId !== latestRequest) return

      const data = result?.data ?? []
      items.value = append ? [...items.value, ...data] : data
      page.value = result?.current_page ?? 1
      lastPage.value = result?.last_page ?? 1
    } finally {
      if (requestId === latestRequest) loadingMore.value = false
    }
  }

  const loadMore = () => {
    if (hasMore.value && !loadingMore.value) void load(page.value + 1)
  }

  /** Clear the search box and reload page 1 — used when a parent filter changes. */
  const refresh = () => {
    debouncedLoad.cancel()
    if (search.value !== '') {
      // The search watcher reloads page 1 once the box is cleared.
      search.value = ''
      return
    }
    void load(1)
  }

  const debouncedLoad = debounce(() => void load(1), debounceMs)
  watch(search, (value) => {
    if (value.trim()) {
      debouncedLoad()
    } else {
      debouncedLoad.cancel()
      void load(1)
    }
  })

  return { items, search, hasMore, loadingMore, load, loadMore, refresh, reset }
}
