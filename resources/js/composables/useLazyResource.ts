import { ref, shallowRef } from 'vue';

/**
 * Data fetched on first use and kept — the shape a tab's content wants.
 *
 * `load()` fetches only when nothing is held yet, so returning to a tab shows what it
 * already loaded instead of asking again; `load(true)` forces a fresh copy. `reset()`
 * forgets it all, for when the subject changes (another user opened in the same pane).
 * Only the latest request may land: a reset or a newer load makes an answer still in
 * flight stale, so a slow reply for the previous subject can never overwrite the next.
 *
 * The fetcher resolves to null on failure (the app's fetchers already notify), which
 * leaves the resource `failed` and retryable rather than throwing into the template.
 */
export function useLazyResource<T>(fetcher: () => Promise<T | null>) {
  const data = shallowRef<T | null>(null);
  const loading = ref(false);
  const failed = ref(false);

  let latestRequest = 0;

  const load = async (force = false): Promise<void> => {
    if (!force && (data.value !== null || loading.value)) return;

    const request = ++latestRequest;
    loading.value = true;
    failed.value = false;

    const result = await fetcher();

    if (request !== latestRequest) return;

    data.value = result;
    failed.value = result === null;
    loading.value = false;
  };

  const reset = () => {
    latestRequest++;
    data.value = null;
    loading.value = false;
    failed.value = false;
  };

  /** Change what is held without a round trip — e.g. after a save returned the new state. */
  const set = (next: T | null) => {
    data.value = next;
  };

  return { data, loading, failed, load, reset, set };
}
