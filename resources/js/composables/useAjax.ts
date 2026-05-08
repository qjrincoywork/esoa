import { applyCsrfHeader, readMetaCsrfToken } from '@/lib/csrf';

export interface AjaxOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    headers?: Record<string, string>;
    body?: BodyInit | Record<string, unknown> | null;
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
        options: AjaxOptions = {},
    ): Promise<AjaxResponse<T>> => {
        const {
            method = 'GET',
            headers = {},
            body = null,
            params,
        } = options;

        // Build full URL with query params
        const fullUrl = params ? `${url}${buildQueryString(params)}` : url;

        const normalizedMethod = method.toUpperCase() as AjaxOptions['method'];
        const isMutatingRequest = normalizedMethod !== 'GET';

        const defaultHeaders: Record<string, string> = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...headers,
        };
        applyCsrfHeader(defaultHeaders);

        let requestBody: BodyInit | null;
        if (body instanceof FormData) {
            requestBody = body;
        } else if (typeof body === 'string' || body instanceof Blob || body instanceof URLSearchParams || body instanceof ArrayBuffer || ArrayBuffer.isView(body) || body instanceof ReadableStream) {
            requestBody = body as BodyInit;
        } else if (body && typeof body === 'object') {
            requestBody = JSON.stringify(body);
            if (!defaultHeaders['Content-Type']) {
                defaultHeaders['Content-Type'] = 'application/json';
            }
        } else {
            requestBody = null;
        }

        if (requestBody instanceof FormData && isMutatingRequest && !requestBody.has('_token')) {
            const plain = readMetaCsrfToken();
            if (plain) {
                requestBody.append('_token', plain);
            }
        }

        try {
            const response = await fetch(fullUrl, {
                method: normalizedMethod,
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
    const post = <T = any>(url: string, body?: BodyInit | Record<string, unknown>, headers?: Record<string, string>): Promise<AjaxResponse<T>> => {
        return request<T>(url, { method: 'POST', body, headers });
    };

    /**
     * Convenience method for PUT requests
     */
    const put = <T = any>(url: string, body?: BodyInit | Record<string, unknown>, headers?: Record<string, string>): Promise<AjaxResponse<T>> => {
        return request<T>(url, { method: 'PUT', body, headers });
    };

    /**
     * Convenience method for PATCH requests
     */
    const patch = <T = any>(url: string, body?: BodyInit | Record<string, unknown>, headers?: Record<string, string>): Promise<AjaxResponse<T>> => {
        return request<T>(url, { method: 'PATCH', body, headers });
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

