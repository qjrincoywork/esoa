export interface AjaxOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    headers?: Record<string, string>;
    body?: BodyInit | null;
    params?: Record<string, string | number>;
}

export interface AjaxResponse<T = any> {
    data: T;
    status: number;
    ok: boolean;
}

/**
 * Plain CSRF token for Laravel: must come from meta[name="csrf-token"] (same as @csrf).
 * Do NOT reuse the XSRF-TOKEN cookie value for X-CSRF-TOKEN or _token — the cookie is
 * often encrypted / meant for X-XSRF-TOKEN decryption on the server; sending it as the
 * plain header breaks VerifyCsrfToken (419 on every request).
 */
function readMetaCsrfToken(): string | null {
  if (typeof document === 'undefined') return null;
  const t = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  return t && t.length > 0 ? t : null;
}

function applyLaravelCsrfHeaders(target: Record<string, string>): void {
  const token = readMetaCsrfToken();
  if (token) {
    target['X-CSRF-TOKEN'] = token;
  }
}

/**
 * Reusable AJAX request composable that doesn't trigger navigation
 * Useful for fetching data without changing the URL
 */
export function useAjax() {
    /**
     * Build query string from params object
     */
    const buildQueryString = (params?: Record<string, string | number>): string => {
        if (!params || Object.keys(params).length === 0) return '';

        const queryParams = new URLSearchParams();
        Object.entries(params).forEach(([key, value]) => {
            queryParams.append(key, String(value));
        });

        return `?${queryParams.toString()}`;
    };

    /**
     * Make an AJAX request without navigation
     */
    const request = async <T = any>(
        url: string,
        options: AjaxOptions = {}
    ): Promise<AjaxResponse<T>> => {
        const {
            method = 'GET',
            headers = {},
            body = null,
            params,
        } = options;

        // Build full URL with query params
        const fullUrl = params ? `${url}${buildQueryString(params)}` : url;

        // Default headers; caller headers merged first so CSRF applied last cannot be overridden
        const defaultHeaders: Record<string, string> = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...headers,
        };
        applyLaravelCsrfHeaders(defaultHeaders);

        const requestBody: BodyInit | null = body instanceof FormData ? body : body ? JSON.stringify(body) : null;
        if (requestBody instanceof FormData && method !== 'GET' && !requestBody.has('_token')) {
          const plain = readMetaCsrfToken();
          if (plain) {
            requestBody.append('_token', plain);
          }
        }

        try {
            const response = await fetch(fullUrl, {
                method,
                headers: defaultHeaders,
                body: requestBody,
                credentials: 'same-origin',
            });

            // Parse JSON response
            const data = await response.json().catch(() => ({}));

            return {
                data: data as T,
                status: response.status,
                ok: response.ok,
            };
        } catch (error) {
            console.error('AJAX request failed:', error);
            throw error;
        }
    };

    /**
     * Convenience method for GET requests
     */
    const get = <T = any>(url: string, params?: Record<string, string | number>): Promise<AjaxResponse<T>> => {
        return request<T>(url, { method: 'GET', params });
    };

    /**
     * Convenience method for POST requests
     */
    const post = <T = any>(url: string, body?: BodyInit | Record<string, any>, headers?: Record<string, string>): Promise<AjaxResponse<T>> => {
        const requestBody = body instanceof FormData ? body : body ? JSON.stringify(body) : null;
        const requestHeaders = body && !(body instanceof FormData)
            ? { 'Content-Type': 'application/json', ...headers }
            : headers;

        return request<T>(url, { method: 'POST', body: requestBody, headers: requestHeaders });
    };

    /**
     * Convenience method for PUT requests
     */
    const put = <T = any>(url: string, body?: BodyInit | Record<string, any>, headers?: Record<string, string>): Promise<AjaxResponse<T>> => {
        const requestBody = body instanceof FormData ? body : body ? JSON.stringify(body) : null;
        const requestHeaders = body && !(body instanceof FormData)
            ? { 'Content-Type': 'application/json', ...headers }
            : headers;

        return request<T>(url, { method: 'PUT', body: requestBody, headers: requestHeaders });
    };

    /**
     * Convenience method for PATCH requests
     */
    const patch = <T = any>(url: string, body?: BodyInit | Record<string, any>, headers?: Record<string, string>): Promise<AjaxResponse<T>> => {
        const requestBody = body instanceof FormData ? body : body ? JSON.stringify(body) : null;
        const requestHeaders = body && !(body instanceof FormData)
            ? { 'Content-Type': 'application/json', ...headers }
            : headers;

        return request<T>(url, { method: 'PATCH', body: requestBody, headers: requestHeaders });
    };

    /**
     * Convenience method for DELETE requests
     */
    const del = <T = any>(url: string, params?: Record<string, string | number>): Promise<AjaxResponse<T>> => {
        return request<T>(url, { method: 'DELETE', params });
    };

    return {
        request,
        get,
        post,
        put,
        patch,
        delete: del,
    };
}

