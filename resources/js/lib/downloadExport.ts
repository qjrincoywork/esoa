/**
 * Download a server-generated export (a spreadsheet streamed by an `*Exporter`).
 *
 * Empty filter values are dropped from the query string, the server's
 * Content-Disposition filename is kept, and a refusal — sent as JSON, e.g. "too many
 * rows" — is thrown with the server's own message so the caller can show it.
 */
export async function downloadExport(
    url: string,
    params: Record<string, string | number | null | undefined>,
    fallbackFilename: string,
): Promise<string> {
    const qs = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== '' && value !== undefined && value !== null) qs.set(key, String(value));
    });

    const response = await fetch(`${url}?${qs.toString()}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            // JSON first, so a validation failure answers 422 with its message instead
            // of redirecting — a redirect would be followed and saved as the "export".
            // The export itself streams regardless of what is accepted.
            Accept: 'application/json, application/vnd.ms-excel',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        let message = 'Export failed.';
        if ((response.headers.get('Content-Type') ?? '').includes('application/json')) {
            const body = (await response.json()) as { message?: string };
            message = body?.message ?? message;
        }
        throw new Error(message);
    }

    const disposition = response.headers.get('Content-Disposition') ?? '';
    const filename = disposition.match(/filename[^;=\n]*=["']?([^"';\n]+)["']?/i)?.[1]?.trim() ?? fallbackFilename;

    const objectUrl = URL.createObjectURL(await response.blob());
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);

    return filename;
}
