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

        // Default headers for AJAX requests
        const defaultHeaders: Record<string, string> = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...headers,
        };

        // Add CSRF token if available (Laravel)
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            defaultHeaders['X-CSRF-TOKEN'] = csrfToken;
        }

        try {
            const response = await fetch(fullUrl, {
                method,
                headers: defaultHeaders,
                body: body instanceof FormData ? body : body ? JSON.stringify(body) : null,
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

