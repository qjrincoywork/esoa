export function readMetaCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token && token.length > 0 ? token : null;
}

export function setMetaCsrfToken(token: string): void {
    if (typeof document === 'undefined' || !token) {
        return;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.setAttribute('content', token);
    }
}

export function applyCsrfHeader(target: Record<string, string>): void {
    const token = readMetaCsrfToken();
    if (token) {
        target['X-CSRF-TOKEN'] = token;
    }
}
