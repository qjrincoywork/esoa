import { onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/** The paging of a listing as the server sends it (a Laravel paginator through `CommonResource`). */
export interface ListingPage {
  current_page: number;
  per_page: number | string;
  total: number;
}

/** The paging a `Datatable` is given and emits back. */
export interface ListingPagination {
  current_page: number;
  per_page: number;
  total: number;
}

/** What one reload asks the server for, decided as it goes out. */
export interface ListingVisit {
  /** The props to reload (Inertia `only`). */
  only: string[];
  onSuccess?: () => void;
}

export interface ServerListingOptions {
  /** The listing the server last sent; local paging follows it. */
  listing: () => ListingPage;
  /** Where the listing is reloaded from. */
  url: () => string;
  /** The current search and filters, as request params. */
  params: () => Record<string, unknown>;
  visit: () => ListingVisit;
}

const toPagination = ({ current_page, per_page, total }: ListingPage): ListingPagination =>
  ({ current_page, per_page: Number(per_page), total });

/**
 * Reloading a server-paginated listing through Inertia partial reloads.
 *
 * Every change — typing, a filter, a page click — goes through one debounced reload
 * (`queueFetch`), so changes made in quick succession make one request rather than one
 * each, and the first page arrives with the page itself. The table reports page changes
 * through `onPaginationChange` rather than having its paging watched, so mirroring the
 * server's reply into it cannot set off another reload.
 */
export function useServerListing(options: ServerListingOptions) {
  const pagination = ref<ListingPagination>(toPagination(options.listing()));
  const isFetching = ref(false);

  let latestVisit = 0;
  let fetchTimer: ReturnType<typeof setTimeout> | undefined;
  let restartPaging = false;

  const fetchNow = () => {
    const visit = ++latestVisit;
    const { only, onSuccess } = options.visit();

    router.get(
      options.url(),
      {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...options.params(),
      },
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only,
        onStart: () => { isFetching.value = true; },
        onSuccess: () => onSuccess?.(),
        // A superseded visit finishes as it is cancelled; only the latest one settles the list.
        onFinish: () => { if (visit === latestVisit) isFetching.value = false; },
      },
    );
  };

  /**
   * Reload the listing after `delay` ms, folding in any reload already waiting.
   * `fromFirstPage` restarts paging, as any change to what is listed must; it holds until
   * the reload goes out, so a page click cannot undo the restart a pending filter asked for.
   */
  const queueFetch = (delay: number, fromFirstPage = true) => {
    restartPaging ||= fromFirstPage;
    clearTimeout(fetchTimer);

    fetchTimer = setTimeout(() => {
      if (restartPaging) pagination.value.current_page = 1;
      restartPaging = false;
      fetchNow();
    }, delay);
  };

  /** A page or page-size change from the table. */
  const onPaginationChange = (next: ListingPagination) => {
    const current = pagination.value;
    if (next.current_page === current.current_page && Number(next.per_page) === current.per_page) return;

    pagination.value = { ...next, per_page: Number(next.per_page) };
    queueFetch(50, false);
  };

  // Keep local paging in step with what the server returned.
  watch(options.listing, (next) => {
    pagination.value = toPagination(next);
  });

  // A reload still waiting when the page is left would navigate straight back to it.
  onBeforeUnmount(() => clearTimeout(fetchTimer));

  return { pagination, isFetching, queueFetch, onPaginationChange };
}
