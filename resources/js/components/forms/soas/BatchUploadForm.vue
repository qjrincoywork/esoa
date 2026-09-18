<script setup lang="ts">
/**
 * Batch billing-invoice upload: one spreadsheet of rows plus the attachments they name.
 *
 * The sheet is parsed here and posted as rows rather than as a file, mirroring the bulk
 * user import; every field is re-validated server-side against the same rules the
 * single-upload form uses, so the parse is a convenience and never the authority.
 *
 * What this screen adds is the part the server cannot do until the request is already
 * made: it matches each row's `file_pdf` / `file_xls` cell against the attachments
 * actually selected and says, before anything is sent, which rows are missing a file.
 * The upload is all-or-nothing on the server, so catching that here saves a round trip
 * over a payload that may be tens of megabytes.
 */
import * as XLSX from 'xlsx';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    UploadCloud,
    Paperclip,
    Download,
    FileSpreadsheet,
    AlertTriangle,
    CheckCircle2,
    XCircle,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';

type Option = { value: string | number; name: string };

interface RowIssue {
    row: number;
    soa_number: string | null;
    messages: string[];
}

interface BatchResult {
    total: number;
    created: number;
    failed: number;
    errors: RowIssue[];
}

const props = defineProps<{
    columns: string[];
    requiredColumns: string[];
    attachmentColumns: string[];
    dateColumns: string[];
    accountTypes: Option[];
    billTypes: Option[];
    statusTypes: Option[];
    maxRows: number;
    maxAttachments: number;
    maxFileSize: number;
    onSubmit: (payload: FormData) => Promise<BatchResult | null>;
    onCancel: () => void;
}>();

const PREVIEW_PAGE_SIZE = 8;

/**
 * The key each row carries its spreadsheet line number under.
 *
 * Kept off the template's own columns — it is not something anyone fills in — and read
 * back by the importer so a reported row number means the same line the uploader is
 * looking at, even though blank rows never get sent.
 */
const LINE_KEY = '__row';

const fileName = ref('');
const parsing = ref(false);
const submitting = ref(false);
const parseError = ref('');
const rows = ref<Record<string, string>[]>([]);
const attachments = ref<File[]>([]);
const missingColumns = ref<string[]>([]);
const missingRequired = ref<string[]>([]);
const result = ref<BatchResult | null>(null);
const previewPage = ref(1);

/** Normalise a header/label into the canonical column key used by the backend. */
const canonical = (value: unknown): string =>
    String(value ?? '').toLowerCase().trim().replace(/\s+/g, '_');

/** A spreadsheet date cell as an ISO day, without a timezone shifting it. */
const toIsoDate = (value: Date): string =>
    `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;

/** The key an attachment is matched on: its file name, case-insensitively. */
const attachmentKey = (name: string): string => name.split(/[\\/]/).pop()?.toLowerCase().trim() ?? '';

const attachmentIndex = computed<Set<string>>(
    () => new Set(attachments.value.map((file) => attachmentKey(file.name))),
);

const rowCount = computed(() => rows.value.length);
const previewTotalPages = computed(() => Math.max(1, Math.ceil(rowCount.value / PREVIEW_PAGE_SIZE)));
const pagedPreviewRows = computed(() => {
    const start = (previewPage.value - 1) * PREVIEW_PAGE_SIZE;
    return rows.value.slice(start, start + PREVIEW_PAGE_SIZE);
});
const previewFrom = computed(() => (rowCount.value === 0 ? 0 : (previewPage.value - 1) * PREVIEW_PAGE_SIZE + 1));
const previewTo = computed(() => Math.min(previewPage.value * PREVIEW_PAGE_SIZE, rowCount.value));
const goToPreviewPage = (page: number) => {
    previewPage.value = Math.min(Math.max(1, page), previewTotalPages.value);
};

/**
 * Everything wrong with the parsed file that can be known without the server.
 *
 * Required cells and the attachment names are checked per row, so the reader gets a
 * row-numbered list matching the one the server would return rather than a single
 * "something is wrong" line.
 */
const clientIssues = computed<RowIssue[]>(() => {
    if (rowCount.value === 0) return [];

    const issues: RowIssue[] = [];
    const claimed = new Map<string, number>();

    rows.value.forEach((row, index) => {
        const line = Number(row[LINE_KEY]) || index + 1;
        const messages: string[] = [];

        props.requiredColumns.forEach((column) => {
            if (!row[column]) messages.push(`${column} is required.`);
        });

        props.attachmentColumns.forEach((column) => {
            const name = row[column];
            if (!name) return;

            const key = attachmentKey(name);

            if (!attachmentIndex.value.has(key)) {
                messages.push(`Attachment '${name}' for ${column} has not been selected.`);
                return;
            }

            const owner = claimed.get(key);
            if (owner !== undefined) {
                messages.push(`Attachment '${name}' is already used by row ${owner}.`);
                return;
            }

            claimed.set(key, line);
        });

        if (messages.length > 0) {
            issues.push({ row: line, soa_number: row.soa_number || null, messages });
        }
    });

    return issues;
});

/**
 * Whether this cell is why its row is being held back.
 *
 * Only the two things the browser can be sure of: a required value left blank, and an
 * attachment named by a file that was never selected. Everything else the server
 * decides, and guessing at it here would mark cells that are perfectly fine.
 */
const cellIsWrong = (row: Record<string, string>, column: string): boolean => {
    if (props.requiredColumns.includes(column) && !row[column]) return true;

    return (
        props.attachmentColumns.includes(column) &&
        !!row[column] &&
        !attachmentIndex.value.has(attachmentKey(row[column]))
    );
};

/** Attachments that were selected but that no row refers to — usually a typo in a cell. */
const unusedAttachments = computed<string[]>(() => {
    if (rowCount.value === 0) return [];

    const referenced = new Set<string>();
    rows.value.forEach((row) => {
        props.attachmentColumns.forEach((column) => {
            if (row[column]) referenced.add(attachmentKey(row[column]));
        });
    });

    return attachments.value
        .filter((file) => !referenced.has(attachmentKey(file.name)))
        .map((file) => file.name);
});

const oversizeAttachments = computed<string[]>(() =>
    attachments.value.filter((file) => file.size / 1024 > props.maxFileSize).map((file) => file.name),
);

const tooManyRows = computed(() => rowCount.value > props.maxRows);
const tooManyAttachments = computed(() => attachments.value.length > props.maxAttachments);

/**
 * How many failing rows the table draws before summarising the rest.
 *
 * A batch can run to thousands of rows and every one of them can fail — rendering a row
 * per failure would lock the browser up at exactly the moment the user needs to read it.
 * The first two hundred are enough to see the pattern; the count says how much follows.
 */
const ISSUE_DISPLAY_LIMIT = 200;

/** The issues on screen: the server's list once it has answered, ours until then. */
const allIssues = computed<RowIssue[]>(() => result.value?.errors ?? clientIssues.value);
const visibleIssues = computed<RowIssue[]>(() => allIssues.value.slice(0, ISSUE_DISPLAY_LIMIT));
const hiddenIssueCount = computed(() => Math.max(0, allIssues.value.length - ISSUE_DISPLAY_LIMIT));

/**
 * Why the upload cannot be sent yet, or null when it can.
 *
 * A disabled button that does not say what it is waiting for leaves the reader guessing
 * — particularly here, where the blocker is often a single cell somewhere in a long
 * file. One reason at a time, in the order they have to be dealt with.
 */
const submitBlockedReason = computed<string | null>(() => {
    if (parsing.value) return 'Reading the file…';
    if (submitting.value) return 'Uploading…';
    if (rowCount.value === 0) return 'Choose an Excel file with at least one row.';
    if (missingRequired.value.length > 0) {
        return `The file is missing the column${missingRequired.value.length > 1 ? 's' : ''} ${missingRequired.value.join(', ')}.`;
    }
    if (tooManyRows.value) return `The file has ${rowCount.value} rows; the limit is ${props.maxRows}.`;
    if (tooManyAttachments.value) {
        return `${attachments.value.length} attachments selected; the limit is ${props.maxAttachments}.`;
    }
    if (oversizeAttachments.value.length > 0) {
        return `${oversizeAttachments.value.length} attachment(s) are larger than ${props.maxFileSize} KB.`;
    }
    if (clientIssues.value.length > 0) {
        const missingFiles = clientIssues.value.filter((issue) =>
            issue.messages.some((message) => message.startsWith('Attachment')),
        ).length;

        return missingFiles > 0
            ? `${missingFiles} row(s) name an attachment that has not been selected — add those files, or correct the file_pdf / file_xls cells.`
            : `${clientIssues.value.length} row(s) are missing a required value. The cells are marked in the preview below.`;
    }

    return null;
});

const canSubmit = computed(() => submitBlockedReason.value === null);

const resetParseState = () => {
    parseError.value = '';
    rows.value = [];
    missingColumns.value = [];
    missingRequired.value = [];
    result.value = null;
    previewPage.value = 1;
};

const onFileChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    fileName.value = file.name;
    resetParseState();
    parsing.value = true;

    try {
        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: 'array', cellDates: true });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const json = XLSX.utils.sheet_to_json<Record<string, unknown>>(sheet, { defval: '', raw: true });

        const rawHeaders = json.length ? Object.keys(json[0]) : [];
        const known = new Set(props.columns);
        const headerMap = new Map<string, string>(); // canonical column -> raw header

        rawHeaders.forEach((header) => {
            const key = canonical(header);
            if (known.has(key)) headerMap.set(key, header);
        });

        const present = new Set(headerMap.keys());
        missingColumns.value = props.columns.filter((c) => !present.has(c));
        missingRequired.value = props.requiredColumns.filter((c) => !present.has(c));

        rows.value = json
            .map((raw, index) => {
                const obj: Record<string, string> = {};
                props.columns.forEach((column) => {
                    const rawHeader = headerMap.get(column);
                    const value = rawHeader !== undefined ? raw[rawHeader] : '';

                    if (value instanceof Date) {
                        obj[column] = toIsoDate(value);
                    } else {
                        obj[column] = value === null || value === undefined ? '' : String(value).trim();
                    }
                });
                // Where this came from in the file: the header is line 1, so the first
                // data row is line 2. Stamped before any filtering, so a reported row
                // number still points at the right line once blanks are dropped.
                obj[LINE_KEY] = String(index + 2);
                return obj;
            })
            // Drop rows the sheet only technically has. A trailing row that Excel keeps
            // alive with a stray space is not a row someone meant to upload, and judging
            // that on "any cell at all" turns one invisible space into eleven errors the
            // uploader cannot see the cause of. A row that carries none of the values an
            // invoice actually needs is not an invoice.
            .filter((obj) => props.requiredColumns.some((c) => obj[c] !== ''));

        if (rows.value.length === 0) {
            parseError.value = 'No data rows were found in the file.';
        }
    } catch {
        parseError.value = 'Could not read the file. Please upload a valid .xlsx, .xls or .csv file.';
        rows.value = [];
    } finally {
        parsing.value = false;
        // Allow re-selecting the same file to re-trigger a change event.
        input.value = '';
    }
};

const onAttachmentsChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const picked = Array.from(input.files ?? []);
    if (picked.length === 0) return;

    // Add to the selection rather than replace it, so the PDFs and the spreadsheets
    // can be chosen in separate passes; a name already held is refreshed in place.
    const byKey = new Map(attachments.value.map((file) => [attachmentKey(file.name), file]));
    picked.forEach((file) => byKey.set(attachmentKey(file.name), file));

    attachments.value = Array.from(byKey.values());
    result.value = null;
    input.value = '';
};

const removeAttachment = (name: string) => {
    attachments.value = attachments.value.filter((file) => file.name !== name);
    result.value = null;
};

const clearAttachments = () => {
    attachments.value = [];
    result.value = null;
};

const downloadTemplate = () => {
    const worksheet = XLSX.utils.aoa_to_sheet([props.columns]);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Billing Invoices');
    XLSX.writeFile(workbook, 'billing_invoice_batch_template.xlsx');
};

const submit = async () => {
    if (!canSubmit.value) return;

    submitting.value = true;
    result.value = null;

    try {
        const payload = new FormData();
        payload.append('rows', JSON.stringify(rows.value));
        attachments.value.forEach((file) => payload.append('attachments[]', file, file.name));

        result.value = await props.onSubmit(payload);
    } finally {
        submitting.value = false;
    }
};

/** Bytes, for the attachment list; the server's limit is expressed in kilobytes. */
const formatSize = (bytes: number): string =>
    bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
</script>

<template>
    <div class="space-y-4">
        <!-- Instructions + template -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-[var(--color-text-muted)]">
                Upload one Excel file listing the billing invoices, then select every PDF and Excel
                attachment it names. The first row must be the column headers.
            </p>
            <Button type="button" variant="outline" size="sm" class="cursor-pointer shrink-0" @click="downloadTemplate">
                <Download class="w-4 h-4 mr-1" /> Download template
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <!-- Spreadsheet -->
            <div class="grid gap-2">
                <Label>Billing invoice list (Excel)</Label>
                <label
                    class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-[var(--color-border-strong)] px-4 py-6 text-center cursor-pointer hover:bg-[var(--color-surface-muted,transparent)] transition-colors">
                    <UploadCloud class="w-6 h-6 text-[var(--color-text-muted)]" />
                    <span class="text-sm text-[var(--color-text)]">
                        <span class="font-medium">Click to choose the Excel file</span>
                    </span>
                    <span v-if="fileName" class="inline-flex items-center gap-1 text-xs text-[var(--color-text-muted)]">
                        <FileSpreadsheet class="w-3.5 h-3.5" /> {{ fileName }}
                    </span>
                    <input type="file" class="hidden" accept=".xlsx,.xls,.csv" @change="onFileChange" />
                </label>
            </div>

            <!-- Attachments -->
            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-2">
                    <Label>Attachments (PDF / Excel)</Label>
                    <Button
                        v-if="attachments.length"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="cursor-pointer h-auto py-0.5 text-xs"
                        @click="clearAttachments">
                        Clear all
                    </Button>
                </div>
                <label
                    class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-[var(--color-border-strong)] px-4 py-6 text-center cursor-pointer hover:bg-[var(--color-surface-muted,transparent)] transition-colors">
                    <Paperclip class="w-6 h-6 text-[var(--color-text-muted)]" />
                    <span class="text-sm text-[var(--color-text)]">
                        <span class="font-medium">Click to choose attachments</span>
                    </span>
                    <span class="text-xs text-[var(--color-text-muted)]">
                        {{ attachments.length }} selected — up to {{ maxAttachments }}, each {{ maxFileSize }} KB max
                    </span>
                    <input
                        type="file"
                        class="hidden"
                        multiple
                        accept=".pdf,.xls,.xlsx"
                        @change="onAttachmentsChange" />
                </label>
            </div>
        </div>

        <!-- Selected attachments -->
        <div v-if="attachments.length" class="rounded-md border border-[var(--color-border)] p-2">
            <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto">
                <span
                    v-for="file in attachments"
                    :key="file.name"
                    class="inline-flex items-center gap-1 rounded-full border border-[var(--color-border)] px-2 py-0.5 text-xs"
                    :class="unusedAttachments.includes(file.name) ? 'text-amber-700 dark:text-amber-400' : 'text-[var(--color-text-muted)]'">
                    {{ file.name }}
                    <span class="opacity-60">({{ formatSize(file.size) }})</span>
                    <button
                        type="button"
                        class="cursor-pointer opacity-60 hover:opacity-100"
                        :aria-label="`Remove ${file.name}`"
                        @click="removeAttachment(file.name)">
                        <XCircle class="w-3 h-3" />
                    </button>
                </span>
            </div>
        </div>

        <!-- Accepted values legend -->
        <details class="rounded-md border border-[var(--color-border)] px-3 py-2 text-xs">
            <summary class="cursor-pointer font-medium text-[var(--color-text)]">Accepted values &amp; rules</summary>
            <div class="mt-2 space-y-1.5 text-[var(--color-text-muted)]">
                <p><span class="font-medium text-[var(--color-text)]">Required columns:</span> {{ requiredColumns.join(', ') }}</p>
                <p><span class="font-medium text-[var(--color-text)]">account_type:</span> optional — derived from the account code. Fill it in only if you want it checked: {{ accountTypes.map(t => `${t.value} = ${t.name}`).join(', ') }}.</p>
                <p><span class="font-medium text-[var(--color-text)]">status:</span> {{ statusTypes.map(s => `${s.value} = ${s.name}`).join(', ') }}</p>
                <p><span class="font-medium text-[var(--color-text)]">bill_type:</span> {{ billTypes.map(b => `${b.value} = ${b.name}`).join(', ') }}</p>
                <p><span class="font-medium text-[var(--color-text)]">Dates ({{ dateColumns.join(', ') }}):</span> use YYYY-MM-DD, or format the cells as dates.</p>
                <p><span class="font-medium text-[var(--color-text)]">file_pdf / file_xls:</span> the file name of an attachment selected above, e.g. BS-000016293.pdf. Each invoice needs its own files; file_xls is not required when bill_type is ECU.</p>
                <p><span class="font-medium text-[var(--color-text)]">account_type, branch_code, contract_date_from, contract_date_to:</span> may be left empty.</p>
                <p>Row numbers below are the line numbers in your spreadsheet, so a reported row is the one to go and open.</p>
                <p>Every row must pass validation — nothing is saved until they all do. Up to {{ maxRows }} rows per upload.</p>
            </div>
        </details>

        <!-- Parse feedback -->
        <p v-if="parsing" class="text-sm text-[var(--color-text-muted)]">Reading file…</p>
        <p v-if="parseError" class="flex items-center gap-1.5 text-sm text-red-600 dark:text-red-400">
            <XCircle class="w-4 h-4" /> {{ parseError }}
        </p>

        <div
            v-if="missingRequired.length"
            class="flex items-start gap-1.5 rounded-md bg-red-50 dark:bg-red-900/20 px-3 py-2 text-xs text-red-700 dark:text-red-400">
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>Missing required column(s): <strong>{{ missingRequired.join(', ') }}</strong>. Add them to the file before uploading.</span>
        </div>
        <div
            v-else-if="missingColumns.length"
            class="flex items-start gap-1.5 rounded-md bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>Optional column(s) not found: <strong>{{ missingColumns.join(', ') }}</strong>. Those values will be left empty.</span>
        </div>

        <div
            v-if="tooManyRows"
            class="flex items-start gap-1.5 rounded-md bg-red-50 dark:bg-red-900/20 px-3 py-2 text-xs text-red-700 dark:text-red-400">
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>The file has {{ rowCount }} rows; at most <strong>{{ maxRows }}</strong> can be uploaded at a time. Split it into smaller files.</span>
        </div>
        <div
            v-if="tooManyAttachments"
            class="flex items-start gap-1.5 rounded-md bg-red-50 dark:bg-red-900/20 px-3 py-2 text-xs text-red-700 dark:text-red-400">
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>{{ attachments.length }} attachments selected; the server accepts at most <strong>{{ maxAttachments }}</strong> per upload.</span>
        </div>
        <div
            v-if="oversizeAttachments.length"
            class="flex items-start gap-1.5 rounded-md bg-red-50 dark:bg-red-900/20 px-3 py-2 text-xs text-red-700 dark:text-red-400">
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>Larger than {{ maxFileSize }} KB: <strong>{{ oversizeAttachments.join(', ') }}</strong>.</span>
        </div>
        <div
            v-if="unusedAttachments.length && !result"
            class="flex items-start gap-1.5 rounded-md bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>No row refers to <strong>{{ unusedAttachments.join(', ') }}</strong>. They will be ignored.</span>
        </div>

        <!-- Result summary -->
        <div v-if="result" class="flex flex-wrap items-center gap-3">
            <span
                v-if="result.created > 0"
                class="inline-flex items-center gap-1.5 rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 px-2.5 py-0.5 text-xs font-medium">
                <CheckCircle2 class="w-3.5 h-3.5" /> {{ result.created }} uploaded
            </span>
            <span
                v-if="result.failed > 0"
                class="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 px-2.5 py-0.5 text-xs font-medium">
                <XCircle class="w-3.5 h-3.5" /> {{ result.failed }} rejected
            </span>
            <span class="text-xs text-[var(--color-text-muted)]">of {{ result.total }} rows</span>
            <span v-if="result.failed > 0" class="text-xs text-[var(--color-text-muted)]">
                Nothing was saved — fix the rows below and upload again.
            </span>
        </div>

        <!-- Row issues: ours before submitting, the server's afterwards -->
        <div v-if="allIssues.length" class="space-y-2">
            <p class="text-sm font-medium text-[var(--color-text)]">
                {{ allIssues.length }} row{{ allIssues.length !== 1 ? 's' : '' }} need
                {{ allIssues.length === 1 ? 's' : '' }} attention
            </p>
            <div class="border border-[var(--color-border)] rounded-md overflow-auto max-h-60">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-[var(--color-surface)]">
                        <tr>
                            <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">Row</th>
                            <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">Billing Invoice</th>
                            <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in visibleIssues" :key="issue.row">
                            <td class="border-b border-[var(--color-border)] px-2 py-1 align-top">{{ issue.row }}</td>
                            <td class="border-b border-[var(--color-border)] px-2 py-1 align-top whitespace-nowrap">{{ issue.soa_number || '—' }}</td>
                            <td class="border-b border-[var(--color-border)] px-2 py-1 align-top">
                                <ul class="list-disc pl-4 space-y-0.5">
                                    <li v-for="(message, mi) in issue.messages" :key="mi">{{ message }}</li>
                                </ul>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="hiddenIssueCount" class="text-xs text-[var(--color-text-muted)]">
                Showing the first {{ ISSUE_DISPLAY_LIMIT }} — and {{ hiddenIssueCount }} more row{{ hiddenIssueCount !== 1 ? 's' : '' }}
                with the same treatment. Fix these and upload again to see the rest.
            </p>
        </div>

        <!-- Preview -->
        <div v-if="rowCount > 0" class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium text-[var(--color-text)]">
                    Preview — {{ rowCount }} row{{ rowCount !== 1 ? 's' : '' }} found
                </p>
                <p v-if="canSubmit" class="inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-400">
                    <CheckCircle2 class="w-3.5 h-3.5" /> Every row has its attachments and required values.
                </p>
            </div>
            <div class="border border-[var(--color-border)] rounded-md overflow-auto max-h-60">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-[var(--color-surface)]">
                        <tr>
                            <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">#</th>
                            <th
                                v-for="col in columns"
                                :key="col"
                                class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium whitespace-nowrap">
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in pagedPreviewRows" :key="i">
                            <td class="border-b border-[var(--color-border)] px-2 py-1 text-[var(--color-text-muted)]">
                                {{ row[LINE_KEY] || previewFrom + i }}
                            </td>
                            <!--
                                A cell is marked when it is the reason a row is held back:
                                a required value left blank, or an attachment name with no
                                matching file. Reading a list of messages and hunting for
                                the cell it means is the slow way round.
                            -->
                            <td
                                v-for="col in columns"
                                :key="col"
                                class="border-b border-[var(--color-border)] px-2 py-1 whitespace-nowrap"
                                :class="cellIsWrong(row, col) ? 'text-red-600 dark:text-red-400 font-medium' : ''">
                                {{ row[col] || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Preview pagination -->
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-[var(--color-text-muted)]">Showing {{ previewFrom }}–{{ previewTo }} of {{ rowCount }}</span>
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="cursor-pointer"
                        :disabled="previewPage <= 1"
                        @click="goToPreviewPage(previewPage - 1)">
                        <ChevronLeft class="w-4 h-4" />
                    </Button>
                    <span class="text-xs text-[var(--color-text-muted)] px-1">Page {{ previewPage }} of {{ previewTotalPages }}</span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="cursor-pointer"
                        :disabled="previewPage >= previewTotalPages"
                        @click="goToPreviewPage(previewPage + 1)">
                        <ChevronRight class="w-4 h-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Actions: the pane draws no footer, so the form carries its own. -->
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-[var(--color-border)] pt-3">
            <p
                v-if="submitBlockedReason && rowCount > 0"
                class="mr-auto flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
                {{ submitBlockedReason }}
            </p>
            <Button type="button" variant="outline" class="cursor-pointer" :disabled="submitting" @click="onCancel">
                Cancel
            </Button>
            <Button type="button" class="cursor-pointer" :disabled="!canSubmit" @click="submit">
                <UploadCloud class="w-4 h-4 mr-1" />
                {{ submitting ? 'Uploading…' : `Upload ${rowCount || ''} ${rowCount === 1 ? 'invoice' : 'invoices'}`.trim() }}
            </Button>
        </div>
    </div>
</template>
