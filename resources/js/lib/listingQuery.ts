/**
 * Reading a server-paginated listing's state back out of its URL.
 *
 * A listing reloads through Inertia with its search and filters as query params
 * (`useServerListing`), so the URL is the one record of what the rows on screen were
 * filtered by. A page that remounts — a browser reload, Back, or the full reload an
 * action ends with — must seed its filter form from that URL, or the form shows empty
 * while the rows are still filtered.
 */

/** The query of an Inertia page URL (`usePage().url`): what the page's props were rendered for. */
export function listingQuery(url: string): URLSearchParams {
  const start = url.indexOf('?');

  return new URLSearchParams(start === -1 ? '' : url.slice(start + 1));
}

/**
 * A filter form's starting values, read from the listing's query.
 *
 * Each field takes the param of the same name — or `params[field]`, where the request
 * calls it differently — and keeps its empty value when the query does not carry it.
 * The same map turns the form back into params ({@link filtersToParams}), so the two
 * directions cannot drift apart.
 */
export function filtersFromQuery<T extends Record<string, string>>(
  empty: T,
  query: URLSearchParams,
  params: Partial<Record<keyof T, string>> = {},
): T {
  const filters = { ...empty };

  for (const field of Object.keys(empty) as (keyof T)[]) {
    const value = query.get(params[field] ?? (field as string));
    if (value !== null) filters[field] = value as T[keyof T];
  }

  return filters;
}

/**
 * A filter form's set fields as request params, named as {@link filtersFromQuery} reads
 * them. '' means "no filter"; '0' is a real choice, so this cannot be a falsy test.
 */
export function filtersToParams<T extends Record<string, string | number>>(
  filters: T,
  params: Partial<Record<keyof T, string>> = {},
): Record<string, string> {
  const result: Record<string, string> = {};

  for (const [field, value] of Object.entries(filters)) {
    if (String(value) !== '') result[params[field as keyof T] ?? field] = String(value);
  }

  return result;
}

/** A switch's state from the query: '1' on, '0' off, and its default when not carried. */
export function switchFromQuery(query: URLSearchParams, param: string, fallback: boolean): boolean {
  const value = query.get(param);

  return value === null ? fallback : value === '1';
}
