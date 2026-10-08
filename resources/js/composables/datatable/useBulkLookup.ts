import { ref, type Ref } from 'vue';
import type { ListingVisit } from '@/composables/datatable/useServerListing';

/** One bulk lookup as a listing runs it: distinct entries, and whether they must match exactly. */
export interface BulkLookup {
  terms: string[];
  exact: boolean;
}

/** How many rows one entry matched, as a listing's `search_term_matches` prop reports it. */
export interface SearchTermMatch {
  term: string;
  count: number;
}

/** The lookup applied to the listing, plus the one entry it is narrowed to (null: every entry's matches). */
export type AppliedBulkLookup = BulkLookup & { focus: string | null };

export interface BulkLookupOptions {
  /** Reload the listing from its first page after `delay` ms — the listing's `queueFetch`. */
  reload: (delay: number) => void;
  /** The single search the bulk lookup stands in for while it is open. */
  search: Ref<string>;
}

/** The prop the server answers the per-entry counts in (`BulkSearchTerms::matches()`). */
const MATCHES_PROP = 'search_term_matches';

/**
 * "Search multiple …" for a server-paginated listing — many entries looked up at once.
 *
 * Pairs with {@link useServerListing}: this owns the lookup's state and turns it into
 * request params, and the listing reloads. The per-entry counts are reloaded only when
 * they can have changed — a new lookup, or a change to the other filters — so paging the
 * results or singling out one entry leaves the server's lazy `search_term_matches` prop,
 * and the aggregate behind it, out of the request.
 *
 * The server side is `AcceptsBulkSearchTerms` (parsing) and `BulkSearchTerms` (matching).
 */
export function useBulkLookup({ reload, search }: BulkLookupOptions) {
  const lookup = ref<AppliedBulkLookup | null>(null);
  const open = ref(false);
  /** Whether the per-entry counts must travel with the next reload. */
  const matchesStale = ref(false);

  /** The lookup as request params, or none. */
  const params = (): Record<string, string> => {
    if (!lookup.value) return {};

    return {
      // One delimited string rather than an array, so it rides in a query string — an
      // export link included; the server splits it on the same separators.
      search_terms: lookup.value.terms.join('\n'),
      ...(lookup.value.exact ? { exact_match: '1' } : {}),
      ...(lookup.value.focus ? { search_term_focus: lookup.value.focus } : {}),
    };
  };

  /**
   * The props a reload asks for: the listing's own, plus the counts when they are
   * stale. A superseded visit never succeeds, so the counts stay stale until a visit
   * that carried them lands.
   */
  const visit = (only: string[]): ListingVisit => {
    const reloadMatches = lookup.value !== null && matchesStale.value;

    return {
      only: reloadMatches ? [...only, MATCHES_PROP] : only,
      onSuccess: () => {
        if (reloadMatches) matchesStale.value = false;
      },
    };
  };

  /** Apply a lookup from page one, listing every entry's matches. */
  const run = (next: BulkLookup) => {
    lookup.value = { ...next, focus: null };
    matchesStale.value = true;
    reload(0);
  };

  /** Narrow the listing to one entry's matches, or back to all of them; the counts are unchanged. */
  const focus = (term: string | null) => {
    if (!lookup.value || lookup.value.focus === term) return;
    lookup.value.focus = term;
    reload(0);
  };

  const clear = () => {
    if (!lookup.value) return;
    lookup.value = null;
    reload(0);
  };

  /**
   * The bulk lookup replaces the single search while open — two searches over the same
   * columns would only narrow each other — and closing it drops whatever it applied.
   */
  const toggle = () => {
    open.value = !open.value;

    if (open.value) {
      search.value = '';
    } else {
      clear();
    }
  };

  /** The other filters changed: the counts, taken under them, must be reloaded too. */
  const markStale = () => {
    matchesStale.value = true;
  };

  return { lookup, open, params, visit, run, focus, clear, toggle, markStale };
}
