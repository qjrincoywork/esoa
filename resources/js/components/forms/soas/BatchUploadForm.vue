<script setup lang="ts">
/**
 * Batch billing-invoice upload: a 3-step wizard (manifest → attach files → review) over
 * one spreadsheet of rows plus the attachments they name.
 *
 * The sheet is parsed here and posted as rows rather than as a file, mirroring the bulk
 * user import; every field is re-validated server-side against the same rules the
 * single-upload form uses, so the parse is a convenience and never the authority.
 *
 * The manifest's file_pdf / file_xls cells name a file **without its extension** — the
 * actual PDF/Excel files are attached in step 2, and matching is done by base name plus
 * the type the column expects (file_pdf only ever matches a .pdf, file_xls only ever
 * matches a .xls/.xlsx), so the uploader never has to type an extension that has to
 * agree with whatever they happened to attach. Where a cell is left blank entirely, it
 * is matched instead by the row's SOA number appearing in the attachment's file name.
 * Either way, this is only ever a convenience: the server re-resolves attachments by
 * the same rule and is the one that decides what is actually missing.
 *
 * "Skip rows with errors" trades the server's default all-or-nothing behavior for
 * uploading whatever passes and reporting the rest.
 *
 * A manifest too large for one HTTP request — PHP enforces its own post_max_size /
 * max_file_uploads before Laravel even runs — is split into several requests sized
 * against the server's real limits (`plannedChunks`, using `postMaxSizeBytes` /
 * `maxFileUploads` from the batch_create metadata) and sent one after another
 * (`submit`). A manifest that fits in one request is exactly one chunk, so this is
 * invisible for the common case; it only becomes visible as the "batch X of Y"
 * progress once a manifest genuinely needs more than one request.
 */
import * as XLSX from 'xlsx';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import BatchUploadRowsTable, { type DisplayRow, type RowStatus } from './BatchUploadRowsTable.vue';
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
    Check,
} from 'lucide-vue-next';

type Option = { value: string | number; name: string };
type Step = 1 | 2 | 3;

interface RowIssue {
    row: number;
    soa_number: string | null;
    messages: string[];
}

interface BatchResult {
    total: number;
    created: number;
    failed: number;
    partial: boolean;
    errors: RowIssue[];
}

/** What one chunk request came back with — un-toasted, since only the form knows whether more chunks follow. */
interface ChunkOutcome {
    ok: boolean;
    message?: string;
    result: BatchResult | null;
}

/** A slice of the manifest sized to fit one request to the server's real upload limits. */
interface Chunk {
    rows: Record<string, string>[];
    files: File[];
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
    /** PHP's max_file_uploads for the /batch_store request — how many files one chunk may carry. */
    maxFileUploads: number;
    /** PHP's post_max_size for the /batch_store request, in bytes — how large one chunk may be. */
    postMaxSizeBytes: number;
    onSubmitChunk: (payload: FormData) => Promise<ChunkOutcome>;
    onComplete: (summary: { created: number; failed: number; stoppedEarly: boolean }) => void;
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

const steps: Array<{ id: Step; label: string }> = [
    { id: 1, label: 'Upload manifest' },
    { id: 2, label: 'Attach files' },
    { id: 3, label: 'Review and submit' },
];
const currentStep = ref<Step>(1);

const manifestInput = ref<HTMLInputElement | null>(null);
const attachmentsInput = ref<HTMLInputElement | null>(null);

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
const skipErrors = ref(false);
const isDraggingManifest = ref(false);
const isDraggingAttachments = ref(false);
const draggingFile = ref<File | null>(null);
const rowDropWarning = ref('');
let warningTimer: ReturnType<typeof setTimeout> | null = null;

/** Which chunk request is in flight, for the progress line; null outside of a submit. */
const chunkProgress = ref<{ current: number; total: number } | null>(null);

/** Normalise a header/label into the canonical column key used by the backend. */
const canonical = (value: unknown): string =>
    String(value ?? '').toLowerCase().trim().replace(/\s+/g, '_');

/** A spreadsheet date cell as an ISO day, without a timezone shifting it. */
const toIsoDate = (value: Date): string =>
    `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;

/** The key a raw file selection is de-duplicated on: its file name, case-insensitively. */
const attachmentKey = (name: string): string => name.split(/[\\/]/).pop()?.toLowerCase().trim() ?? '';

/** Non-alphanumeric characters stripped and lower-cased, for a loose substring match. */
const referenceKey = (value: string): string => value.toLowerCase().replace(/[^a-z0-9]/g, '');

/** A file name without its directory or its extension, lower-cased and trimmed. */
const baseNameOf = (name: string): string => {
    const stripped = name.split(/[\\/]/).pop() ?? '';
    const dot = stripped.lastIndexOf('.');
    return (dot > 0 ? stripped.slice(0, dot) : stripped).toLowerCase().trim();
};

/** A file name's extension, lower-cased, without the leading dot. */
const extensionOf = (name: string): string => {
    const stripped = name.split(/[\\/]/).pop() ?? '';
    const dot = stripped.lastIndexOf('.');
    return dot > 0 ? stripped.slice(dot + 1).toLowerCase() : '';
};

/** Which family an extension belongs to, or null when it is neither a PDF nor an Excel file. */
const categoryOfExtension = (extension: string): 'pdf' | 'xls' | null => {
    if (extension === 'pdf') return 'pdf';
    if (extension === 'xls' || extension === 'xlsx') return 'xls';
    return null;
};

/** Which family a column expects — file_pdf only ever wants a PDF, every other attachment column wants Excel. */
const categoryOfColumn = (column: string): 'pdf' | 'xls' => (column.toLowerCase().includes('pdf') ? 'pdf' : 'xls');

/**
 * The key an uploaded file is matched on: its type plus its base name, so a `.pdf` and
 * an `.xlsx` sharing the same base name (the normal case for one invoice) stay distinct
 * instead of colliding, while the extension itself never has to be typed into a cell.
 * Null when the file is neither a PDF nor an Excel file — such a file can never resolve
 * to any column, and is left to show up as unmatched instead.
 */
const attachmentCompositeKey = (name: string): string | null => {
    const category = categoryOfExtension(extensionOf(name));
    return category ? `${category}:${baseNameOf(name)}` : null;
};

/** The same key a cell would need to match, for the type its column expects. */
const cellCompositeKey = (value: string, column: string): string => `${categoryOfColumn(column)}:${baseNameOf(value)}`;

const attachmentIndex = computed<Set<string>>(() => {
    const set = new Set<string>();
    attachments.value.forEach((file) => {
        const key = attachmentCompositeKey(file.name);
        if (key) set.add(key);
    });
    return set;
});

const lineOf = (row: Record<string, string>): number => Number(row[LINE_KEY]) || 0;

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
        const line = lineOf(row) || index + 1;
        const messages: string[] = [];

        props.requiredColumns.forEach((column) => {
            if (!row[column]) messages.push(`${column} is required.`);
        });

        props.attachmentColumns.forEach((column) => {
            const name = row[column];
            if (!name) return;

            const key = cellCompositeKey(name, column);

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
        !attachmentIndex.value.has(cellCompositeKey(row[column], column))
    );
};

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

/** The issues in play: the server's list once it has answered, ours until then. */
const allIssues = computed<RowIssue[]>(() => result.value?.errors ?? clientIssues.value);
const visibleIssues = computed<RowIssue[]>(() => allIssues.value.slice(0, ISSUE_DISPLAY_LIMIT));
const hiddenIssueCount = computed(() => Math.max(0, allIssues.value.length - ISSUE_DISPLAY_LIMIT));

/** O(1) lookup from a spreadsheet line to its issue, built once per render pass. */
const issuesByLine = computed<Map<number, RowIssue>>(() => {
    const map = new Map<number, RowIssue>();
    allIssues.value.forEach((issue) => map.set(issue.row, issue));
    return map;
});

/** Ready / missing-attachment / invalid, and the label the status badge shows. */
const statusOf = (row: Record<string, string>): RowStatus => {
    const issue = issuesByLine.value.get(lineOf(row));
    if (!issue) return { kind: 'ready', label: 'Ready' };

    const nonAttachmentMessages = issue.messages.filter((message) => !message.startsWith('Attachment'));
    if (nonAttachmentMessages.length > 0) return { kind: 'invalid', label: 'Invalid' };

    const missingNames = props.attachmentColumns
        .filter((column) => cellIsWrong(row, column))
        .map((column) => (column.toLowerCase().includes('pdf') ? 'PDF' : 'XLSX'));

    return { kind: 'missing', label: missingNames.length ? `Missing ${missingNames.join(', ')}` : 'Missing file' };
};

const rowStatusCounts = computed(() => {
    const counts = { ready: 0, missing: 0, invalid: 0 };
    rows.value.forEach((row) => {
        counts[statusOf(row).kind] += 1;
    });
    return counts;
});

const pagedDisplayRows = computed<DisplayRow[]>(() =>
    pagedPreviewRows.value.map((row) => ({ row, line: lineOf(row), status: statusOf(row) })),
);

/** Which column an uploaded file belongs to, going only by its extension. */
const attachmentTypeColumn = (file: File): string | null => {
    const category = categoryOfExtension(extensionOf(file.name));
    if (!category) return null;
    return props.attachmentColumns.find((c) => categoryOfColumn(c) === category) ?? null;
};

const isCompatibleFile = (file: File, column: string): boolean =>
    categoryOfExtension(extensionOf(file.name)) === categoryOfColumn(column);

/**
 * Fill empty attachment cells by matching an uploaded file's name against each row's
 * SOA number, so the uploader is not required to type exact file names into the sheet.
 *
 * Never overwrites a cell that already has a value — the sheet's own value, and any
 * earlier manual assignment, always wins. A file name matching more than one row is
 * left alone rather than guessed at; it shows up as unmatched instead.
 */
const autoMatchAttachments = () => {
    if (rowCount.value === 0 || attachments.value.length === 0) return;

    const claimed = new Set<string>();
    rows.value.forEach((row) => {
        props.attachmentColumns.forEach((column) => {
            if (row[column]) claimed.add(cellCompositeKey(row[column], column));
        });
    });

    attachments.value.forEach((file) => {
        const column = attachmentTypeColumn(file);
        if (!column) return;

        const key = attachmentCompositeKey(file.name);
        if (!key || claimed.has(key)) return;

        const fileKey = referenceKey(baseNameOf(file.name));
        const candidates = rows.value.filter(
            (row) => !row[column] && row.soa_number && fileKey.includes(referenceKey(row.soa_number)),
        );

        if (candidates.length === 1) {
            // Named without its extension: the cell records a reference, not a file path.
            candidates[0][column] = baseNameOf(file.name);
            claimed.add(key);
        }
    });
};

/** Attachment slots any row currently refers to — a file outside this set is unmatched. */
const referencedAttachmentKeys = computed<Set<string>>(() => {
    const set = new Set<string>();
    rows.value.forEach((row) => {
        props.attachmentColumns.forEach((column) => {
            if (row[column]) set.add(cellCompositeKey(row[column], column));
        });
    });
    return set;
});

const unmatchedFiles = computed<File[]>(() =>
    attachments.value.filter((file) => {
        const key = attachmentCompositeKey(file.name);
        return key === null || !referencedAttachmentKeys.value.has(key);
    }),
);

/** Why a file could not be auto-matched, for the banner listing it. */
const explainUnmatched = (file: File): string => {
    const column = attachmentTypeColumn(file);
    if (!column) return 'not a PDF or Excel file';

    const fileKey = referenceKey(baseNameOf(file.name));
    const candidates = rows.value.filter(
        (row) => !row[column] && row.soa_number && fileKey.includes(referenceKey(row.soa_number)),
    );
    if (candidates.length > 1) return `matches ${candidates.length} rows — attach it manually`;

    const alreadyTaken = rows.value.some(
        (row) => row.soa_number && fileKey.includes(referenceKey(row.soa_number)),
    );
    return alreadyTaken ? 'that row already has a file' : 'no matching row';
};

/** Point one row's attachment cell at a specific file, freeing it from wherever it was. */
const assignFileToRow = (row: Record<string, string>, column: string, file: File) => {
    const selectionKey = attachmentKey(file.name);

    if (!attachments.value.some((f) => attachmentKey(f.name) === selectionKey)) {
        attachments.value = [...attachments.value, file];
    }

    const compositeKey = attachmentCompositeKey(file.name);
    if (compositeKey) {
        rows.value.forEach((r) => {
            props.attachmentColumns.forEach((col) => {
                if (r[col] && cellCompositeKey(r[col], col) === compositeKey) r[col] = '';
            });
        });
    }

    // Named without its extension: the cell records a reference, not a file path.
    row[column] = baseNameOf(file.name);
    result.value = null;
};

const showRowDropWarning = (message: string) => {
    rowDropWarning.value = message;
    if (warningTimer) clearTimeout(warningTimer);
    warningTimer = setTimeout(() => {
        rowDropWarning.value = '';
    }, 4000);
};

const onRowDrop = ({ row, column, event }: { row: Record<string, string>; column: string; event: DragEvent }) => {
    const dropped = event.dataTransfer?.files?.length ? event.dataTransfer.files[0] : draggingFile.value;
    draggingFile.value = null;
    if (!dropped) return;

    if (!isCompatibleFile(dropped, column)) {
        showRowDropWarning(`${dropped.name} is not a ${column.toLowerCase().includes('pdf') ? 'PDF' : 'Excel'} file.`);
        return;
    }

    assignFileToRow(row, column, dropped);
};

const onRowFile = ({ row, column, file }: { row: Record<string, string>; column: string; file: File }) => {
    assignFileToRow(row, column, file);
};

const resetParseState = () => {
    parseError.value = '';
    rows.value = [];
    missingColumns.value = [];
    missingRequired.value = [];
    result.value = null;
    previewPage.value = 1;
};

const processManifestFile = async (file: File) => {
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
            // Drop rows the sheet only technically has — see the original note: a row
            // carrying none of the values an invoice actually needs is not an invoice.
            .filter((obj) => props.requiredColumns.some((c) => obj[c] !== ''));

        if (rows.value.length === 0) {
            parseError.value = 'No data rows were found in the file.';
        } else {
            autoMatchAttachments();
        }
    } catch {
        parseError.value = 'Could not read the file. Please upload a valid .xlsx, .xls or .csv file.';
        rows.value = [];
    } finally {
        parsing.value = false;
    }
};

const onManifestInputChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = ''; // allow re-selecting the same file to re-trigger a change event
    if (file) await processManifestFile(file);
};

const onManifestDrop = async (event: DragEvent) => {
    isDraggingManifest.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) await processManifestFile(file);
};

const addAttachments = (files: File[]) => {
    if (files.length === 0) return;

    // Add to the selection rather than replace it, so files can be chosen across
    // several drops or browses; a name already held is refreshed in place.
    const byKey = new Map(attachments.value.map((file) => [attachmentKey(file.name), file]));
    files.forEach((file) => byKey.set(attachmentKey(file.name), file));

    attachments.value = Array.from(byKey.values());
    result.value = null;
    autoMatchAttachments();
};

const onAttachmentsInputChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const picked = Array.from(input.files ?? []);
    input.value = '';
    addAttachments(picked);
};

const onAttachmentsDrop = (event: DragEvent) => {
    isDraggingAttachments.value = false;
    addAttachments(Array.from(event.dataTransfer?.files ?? []));
};

const removeAttachment = (name: string) => {
    const selectionKey = attachmentKey(name);
    const compositeKey = attachmentCompositeKey(name);

    attachments.value = attachments.value.filter((file) => attachmentKey(file.name) !== selectionKey);

    if (compositeKey) {
        rows.value.forEach((row) => {
            props.attachmentColumns.forEach((column) => {
                if (row[column] && cellCompositeKey(row[column], column) === compositeKey) row[column] = '';
            });
        });
    }
    result.value = null;
};

const clearAttachments = () => {
    attachments.value = [];
    rows.value.forEach((row) => {
        props.attachmentColumns.forEach((column) => {
            row[column] = '';
        });
    });
    result.value = null;
};

const downloadTemplate = () => {
    const worksheet = XLSX.utils.aoa_to_sheet([props.columns]);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Billing Invoices');
    XLSX.writeFile(workbook, 'billing_invoice_batch_template.xlsx');
};

/** A manifest must be parsed cleanly before there is anything to attach files to. */
const canProceedToAttachments = computed(
    () => !parsing.value && rowCount.value > 0 && missingRequired.value.length === 0 && !tooManyRows.value,
);

const goNext = () => {
    if (currentStep.value === 1 && !canProceedToAttachments.value) return;
    if (currentStep.value < 3) currentStep.value = (currentStep.value + 1) as Step;
};
const goBack = () => {
    if (currentStep.value > 1) currentStep.value = (currentStep.value - 1) as Step;
};
const goToStep = (step: Step) => {
    if (step > 1 && !canProceedToAttachments.value) return;
    currentStep.value = step;
};

const readyCount = computed(() => rowStatusCounts.value.ready);

/**
 * Why the upload cannot be sent yet, or null when it can.
 *
 * A disabled button that does not say what it is waiting for leaves the reader guessing
 * — particularly here, where the blocker is often a single cell somewhere in a long
 * file. One reason at a time, in the order it has to be dealt with. Limits that the
 * server would refuse outright always block, whether or not errors are being skipped.
 */
const hardBlockReason = computed<string | null>(() => {
    if (parsing.value) return 'Reading the file…';
    if (submitting.value) return 'Uploading…';
    if (rowCount.value === 0) return 'Upload a manifest with at least one row.';
    if (missingRequired.value.length > 0) {
        return `The manifest is missing the column${missingRequired.value.length > 1 ? 's' : ''} ${missingRequired.value.join(', ')}.`;
    }
    if (tooManyRows.value) return `The manifest has ${rowCount.value} rows; the limit is ${props.maxRows}.`;
    if (tooManyAttachments.value) {
        return `${attachments.value.length} attachments selected; the limit is ${props.maxAttachments}.`;
    }
    if (oversizeAttachments.value.length > 0) {
        return `${oversizeAttachments.value.length} attachment(s) are larger than ${props.maxFileSize} KB.`;
    }
    return null;
});

const submitBlockedReason = computed<string | null>(() => {
    if (hardBlockReason.value) return hardBlockReason.value;

    if (skipErrors.value) {
        return readyCount.value > 0
            ? null
            : 'No rows are ready to upload yet — fix at least one row, or attach the file it names.';
    }

    return clientIssues.value.length > 0
        ? `${clientIssues.value.length} row(s) need attention before this can be uploaded — fix them, or check "Skip rows with errors" below.`
        : null;
});

const canSubmit = computed(() => submitBlockedReason.value === null);

/**
 * How many files one request may carry / how large it may be, safely under the
 * server's real PHP limits. PHP enforces these before Laravel ever runs, so sizing
 * a chunk any closer to the raw limit risks landing exactly on a boundary some
 * environment enforces slightly differently (multipart boundary overhead, the
 * `rows`/`skip_errors` fields riding alongside the files, etc).
 */
const CHUNK_FILE_COUNT_MARGIN = 2;
const CHUNK_BYTES_SAFETY_FACTOR = 0.7;
const CHUNK_MAX_ROW_CAP = 200;

/**
 * Split the manifest into requests sized to fit the server's real upload limits.
 *
 * A row's own attachments always travel with it, so a chunk boundary is only ever
 * placed *between* rows — never overflowing on the very first row added to an empty
 * chunk, however large it is on its own, so the loop always makes progress. A
 * manifest small enough for a single request produces exactly one chunk, which is
 * why a normal-sized batch behaves identically to a single, non-chunked upload.
 */
const plannedChunks = computed<Chunk[]>(() => {
    if (rowCount.value === 0) return [];

    const attachmentByKey = new Map<string, File>();
    attachments.value.forEach((file) => {
        const key = attachmentCompositeKey(file.name);
        if (key) attachmentByKey.set(key, file);
    });

    const maxBytes = Math.max(1, Math.floor(props.postMaxSizeBytes * CHUNK_BYTES_SAFETY_FACTOR));
    const maxFiles = Math.max(1, props.maxFileUploads - CHUNK_FILE_COUNT_MARGIN);
    const maxRows = Math.min(CHUNK_MAX_ROW_CAP, maxFiles * 2);

    const chunks: Chunk[] = [];
    let currentRows: Record<string, string>[] = [];
    let currentFiles: File[] = [];
    let currentBytes = 0;

    rows.value.forEach((row) => {
        const rowFiles: File[] = [];
        let rowBytes = 0;

        props.attachmentColumns.forEach((column) => {
            const value = row[column];
            const file = value ? attachmentByKey.get(cellCompositeKey(value, column)) : undefined;
            if (file) {
                rowFiles.push(file);
                rowBytes += file.size;
            }
        });

        const overflows = currentRows.length > 0 && (
            currentRows.length >= maxRows ||
            currentFiles.length + rowFiles.length > maxFiles ||
            currentBytes + rowBytes > maxBytes
        );

        if (overflows) {
            chunks.push({ rows: currentRows, files: currentFiles });
            currentRows = [];
            currentFiles = [];
            currentBytes = 0;
        }

        currentRows.push(row);
        currentFiles.push(...rowFiles);
        currentBytes += rowBytes;
    });

    if (currentRows.length > 0) chunks.push({ rows: currentRows, files: currentFiles });

    return chunks;
});

const buildChunkPayload = (chunk: Chunk): FormData => {
    const payload = new FormData();
    payload.append('rows', JSON.stringify(chunk.rows));
    payload.append('skip_errors', skipErrors.value ? '1' : '0');
    chunk.files.forEach((file) => payload.append('attachments[]', file, file.name));
    return payload;
};

const submitLabel = computed(() => {
    if (chunkProgress.value) return `Uploading batch ${chunkProgress.value.current} of ${chunkProgress.value.total}…`;
    if (submitting.value) return 'Uploading…';
    const count = skipErrors.value && clientIssues.value.length > 0 ? readyCount.value : rowCount.value;
    const label = `Upload ${count || ''} ${count === 1 ? 'invoice' : 'invoices'}`.trim();
    return plannedChunks.value.length > 1 ? `${label} (${plannedChunks.value.length} batches)` : label;
});

/**
 * Send the manifest as one or more chunk requests, in order.
 *
 * Without "skip rows with errors", a chunk that fails validation stops the whole
 * batch there rather than sending the rest — true all-or-nothing cannot span
 * several independent HTTP requests, so this is the closest honest equivalent: rows
 * already uploaded in earlier chunks stay uploaded, and nothing further is attempted
 * until the uploader has seen what went wrong. A network failure always stops the
 * batch, since what the server actually did with that request is unknown.
 */
const submit = async () => {
    if (!canSubmit.value) return;

    submitting.value = true;
    result.value = null;

    try {
        const originalTotal = rowCount.value;
        const chunks = plannedChunks.value;

        const attemptedLines = new Set<number>();
        const allErrors: RowIssue[] = [];
        let totalCreated = 0;
        let stoppedEarly = false;
        let networkErrorMessage: string | null = null;

        for (let i = 0; i < chunks.length; i += 1) {
            chunkProgress.value = { current: i + 1, total: chunks.length };

            const chunk = chunks[i];
            chunk.rows.forEach((row) => attemptedLines.add(lineOf(row)));

            const outcome = await props.onSubmitChunk(buildChunkPayload(chunk));

            if (outcome.result) {
                totalCreated += outcome.result.created;
                allErrors.push(...outcome.result.errors);
            }

            const isNetworkFailure = !outcome.ok && outcome.result === null;
            const isFullRejection = !outcome.ok && outcome.result !== null;

            if (isNetworkFailure) {
                stoppedEarly = true;
                networkErrorMessage = outcome.message ?? 'Network error';
                break;
            }
            if (isFullRejection && !skipErrors.value) {
                stoppedEarly = true;
                break;
            }
        }

        // A row in a chunk the batch never reached is not "invalid" — say so, rather
        // than letting it silently vanish from the issues list.
        if (stoppedEarly) {
            rows.value.forEach((row) => {
                const line = lineOf(row);
                if (!attemptedLines.has(line)) {
                    allErrors.push({
                        row: line,
                        soa_number: row.soa_number || null,
                        messages: [
                            networkErrorMessage
                                ? `Not attempted — the batch stopped after a network error (${networkErrorMessage}).`
                                : 'Not attempted — the batch stopped at an earlier row that failed validation.',
                        ],
                    });
                }
            });
        }

        const failed = originalTotal - totalCreated;
        result.value = {
            total: originalTotal,
            created: totalCreated,
            failed,
            partial: totalCreated > 0 && failed > 0,
            errors: allErrors,
        };

        // Keep only the rows that still need attention, so a retry does not try to
        // create the ones that already succeeded a second time.
        const stillNeedsAttention = new Set(allErrors.map((issue) => issue.row));
        rows.value = rows.value.filter((row) => stillNeedsAttention.has(lineOf(row)));
        previewPage.value = 1;

        props.onComplete({ created: totalCreated, failed, stoppedEarly });
    } finally {
        submitting.value = false;
        chunkProgress.value = null;
    }
};

/** Bytes, for the attachment list; the server's limit is expressed in kilobytes. */
const formatSize = (bytes: number): string =>
    bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Step indicator -->
        <nav class="flex flex-wrap items-center gap-2">
            <template v-for="(step, index) in steps" :key="step.id">
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-md px-1.5 py-1 text-sm transition-colors"
                    :class="currentStep === step.id ? 'text-[var(--color-text)] font-medium' : 'text-[var(--color-text-muted)]'"
                    :disabled="step.id > 1 && !canProceedToAttachments"
                    @click="goToStep(step.id)">
                    <span
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px]"
                        :class="currentStep === step.id
                            ? 'bg-[var(--primary-color)] text-white'
                            : currentStep > step.id
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                : 'border border-[var(--color-border-strong)]'">
                        <Check v-if="currentStep > step.id" class="w-3 h-3" />
                        <template v-else>{{ step.id }}</template>
                    </span>
                    {{ step.label }}
                </button>
                <ChevronRight v-if="index < steps.length - 1" class="w-4 h-4 shrink-0 text-[var(--color-text-muted)]" />
            </template>
        </nav>

        <!-- Step 1: Upload manifest -->
        <div v-if="currentStep === 1" class="space-y-4">
            <p class="text-sm text-[var(--color-text-muted)]">
                Upload one Excel file listing the billing invoices. The first row must be the column headers.
            </p>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Manifest</Label>
                    <div
                        class="flex flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed px-4 py-8 text-center cursor-pointer transition-colors"
                        :class="isDraggingManifest
                            ? 'border-[var(--primary-color)] bg-[var(--color-surface-muted,transparent)]'
                            : 'border-[var(--color-border-strong)] hover:bg-[var(--color-surface-muted,transparent)]'"
                        @dragover.prevent="isDraggingManifest = true"
                        @dragleave.prevent="isDraggingManifest = false"
                        @drop.prevent="onManifestDrop"
                        @click="manifestInput?.click()">
                        <UploadCloud class="w-6 h-6 text-[var(--color-text-muted)]" />
                        <span class="text-sm text-[var(--color-text)]">
                            <span class="font-medium">Drag and drop, or click to browse</span>
                        </span>
                        <span class="text-xs text-[var(--color-text-muted)]">.xlsx, .xls or .csv</span>
                        <input
                            ref="manifestInput"
                            type="file"
                            class="hidden"
                            accept=".xlsx,.xls,.csv"
                            @click.stop
                            @change="onManifestInputChange" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Sample manifest</Label>
                    <div class="flex flex-col items-center justify-center gap-2 rounded-md border border-[var(--color-border)] px-4 py-8 text-center">
                        <FileSpreadsheet class="w-6 h-6 text-[var(--color-text-muted)]" />
                        <p class="text-sm text-[var(--color-text)]">Not sure of the format?</p>
                        <p class="text-xs text-[var(--color-text-muted)]">
                            Download the template with the exact columns this importer expects.
                        </p>
                        <Button type="button" variant="outline" size="sm" class="cursor-pointer" @click="downloadTemplate">
                            <Download class="w-4 h-4 mr-1" /> Download template
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Manifest summary, once one has been parsed -->
            <div
                v-if="fileName"
                class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-[var(--color-border)] px-3 py-2">
                <div class="flex min-w-0 items-center gap-2 text-sm">
                    <FileSpreadsheet class="w-4 h-4 shrink-0 text-[var(--color-text-muted)]" />
                    <span class="font-medium truncate">{{ fileName }}</span>
                    <span v-if="!parsing" class="shrink-0 text-xs text-[var(--color-text-muted)]">
                        {{ rowCount }} row{{ rowCount !== 1 ? 's' : '' }} parsed
                        · {{ missingRequired.length + missingColumns.length }} header error{{ (missingRequired.length + missingColumns.length) !== 1 ? 's' : '' }}
                    </span>
                </div>
            </div>

            <p v-if="parsing" class="text-sm text-[var(--color-text-muted)]">Reading file…</p>
            <p v-if="parseError" class="flex items-center gap-1.5 text-sm text-red-600 dark:text-red-400">
                <XCircle class="w-4 h-4" /> {{ parseError }}
            </p>

            <div
                v-if="missingRequired.length"
                class="flex items-start gap-1.5 rounded-md bg-red-50 dark:bg-red-900/20 px-3 py-2 text-xs text-red-700 dark:text-red-400">
                <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
                <span>Missing required column(s): <strong>{{ missingRequired.join(', ') }}</strong>. Add them to the file before continuing.</span>
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

            <details class="rounded-md border border-[var(--color-border)] px-3 py-2 text-xs">
                <summary class="cursor-pointer font-medium text-[var(--color-text)]">Accepted values &amp; rules</summary>
                <div class="mt-2 space-y-1.5 text-[var(--color-text-muted)]">
                    <p><span class="font-medium text-[var(--color-text)]">Required columns:</span> {{ requiredColumns.join(', ') }}</p>
                    <p><span class="font-medium text-[var(--color-text)]">account_type:</span> optional — derived from the account code. Fill it in only if you want it checked: {{ accountTypes.map(t => `${t.value} = ${t.name}`).join(', ') }}.</p>
                    <p><span class="font-medium text-[var(--color-text)]">status:</span> {{ statusTypes.map(s => `${s.value} = ${s.name}`).join(', ') }}</p>
                    <p><span class="font-medium text-[var(--color-text)]">bill_type:</span> {{ billTypes.map(b => `${b.value} = ${b.name}`).join(', ') }}</p>
                    <p><span class="font-medium text-[var(--color-text)]">Dates ({{ dateColumns.join(', ') }}):</span> use YYYY-MM-DD, or format the cells as dates.</p>
                    <p><span class="font-medium text-[var(--color-text)]">file_pdf / file_xls:</span> the attachment's file name <strong>without its extension</strong> (e.g. <code>SOA-000123</code>, not <code>SOA-000123.pdf</code>) — matched against whatever PDF/Excel file you attach in the next step. Leave blank to auto-match by SOA number instead. file_xls is not required when bill_type is ECU.</p>
                    <p><span class="font-medium text-[var(--color-text)]">account_type, branch_code, contract_date_from, contract_date_to:</span> may be left empty.</p>
                    <p>Every row must pass validation unless "Skip rows with errors" is checked in the last step. Up to {{ maxRows }} rows per upload.</p>
                </div>
            </details>
        </div>

        <!-- Step 2: Attach files -->
        <div v-else-if="currentStep === 2" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex items-center justify-between gap-3 rounded-md border border-[var(--color-border)] px-3 py-3">
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <FileSpreadsheet class="w-5 h-5 shrink-0 text-[var(--color-text-muted)]" />
                        <div class="min-w-0">
                            <p class="font-medium truncate">{{ fileName || 'Manifest' }}</p>
                            <p class="text-xs text-[var(--color-text-muted)]">{{ rowCount }} row{{ rowCount !== 1 ? 's' : '' }} parsed</p>
                        </div>
                    </div>
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer shrink-0" @click="currentStep = 1">
                        Replace
                    </Button>
                </div>

                <div
                    class="flex flex-col items-center justify-center gap-1.5 rounded-md border-2 border-dashed px-4 py-3 text-center cursor-pointer transition-colors"
                    :class="isDraggingAttachments
                        ? 'border-[var(--primary-color)] bg-[var(--color-surface-muted,transparent)]'
                        : 'border-[var(--color-border-strong)] hover:bg-[var(--color-surface-muted,transparent)]'"
                    @dragover.prevent="isDraggingAttachments = true"
                    @dragleave.prevent="isDraggingAttachments = false"
                    @drop.prevent="onAttachmentsDrop"
                    @click="attachmentsInput?.click()">
                    <Paperclip class="w-5 h-5 text-[var(--color-text-muted)]" />
                    <span class="text-sm"><span class="font-medium">Drop PDF and XLSX attachments here</span></span>
                    <span class="text-xs text-[var(--color-text-muted)]">
                        Matched to rows by name (extension ignored) or by SOA number, e.g. {{ (rows.find(r => r.soa_number)?.soa_number) || 'SOA-000123' }}.pdf
                    </span>
                    <span class="text-xs text-[var(--color-text-muted)]">
                        {{ attachments.length }} selected — up to {{ maxAttachments }}, each {{ maxFileSize }} KB max
                    </span>
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer" @click.stop="attachmentsInput?.click()">
                        Browse files
                    </Button>
                    <input
                        ref="attachmentsInput"
                        type="file"
                        class="hidden"
                        multiple
                        accept=".pdf,.xls,.xlsx"
                        @click.stop
                        @change="onAttachmentsInputChange" />
                </div>
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
            <p v-if="rowDropWarning" class="flex items-center gap-1.5 text-xs text-red-600 dark:text-red-400">
                <XCircle class="w-3.5 h-3.5" /> {{ rowDropWarning }}
            </p>

            <BatchUploadRowsTable
                :columns="columns"
                :attachment-columns="attachmentColumns"
                :items="pagedDisplayRows"
                :cell-is-wrong="cellIsWrong"
                @row-drop="onRowDrop"
                @row-file="onRowFile" />

            <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-[var(--color-text-muted)]">Showing {{ previewFrom }}–{{ previewTo }} of {{ rowCount }}</span>
                <div class="flex items-center gap-1">
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer" :disabled="previewPage <= 1" @click="goToPreviewPage(previewPage - 1)">
                        <ChevronLeft class="w-4 h-4" />
                    </Button>
                    <span class="text-xs text-[var(--color-text-muted)] px-1">Page {{ previewPage }} of {{ previewTotalPages }}</span>
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer" :disabled="previewPage >= previewTotalPages" @click="goToPreviewPage(previewPage + 1)">
                        <ChevronRight class="w-4 h-4" />
                    </Button>
                </div>
            </div>

            <div
                v-if="unmatchedFiles.length"
                class="rounded-md border border-amber-300/60 bg-amber-50 dark:bg-amber-900/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-300">
                <p class="flex items-center gap-1.5 font-medium">
                    <AlertTriangle class="w-3.5 h-3.5" /> {{ unmatchedFiles.length }} file{{ unmatchedFiles.length !== 1 ? 's' : '' }} couldn't be matched
                </p>
                <ul class="mt-1.5 flex flex-wrap gap-1.5">
                    <li
                        v-for="file in unmatchedFiles"
                        :key="file.name"
                        draggable="true"
                        class="inline-flex items-center gap-1 rounded-full border border-amber-400/60 bg-[var(--color-surface)] px-2 py-0.5 cursor-grab active:cursor-grabbing"
                        @dragstart="draggingFile = file"
                        @dragend="draggingFile = null">
                        {{ file.name }} <span class="opacity-70">({{ explainUnmatched(file) }})</span>
                        <button type="button" class="opacity-60 hover:opacity-100" :aria-label="`Remove ${file.name}`" @click="removeAttachment(file.name)">
                            <XCircle class="w-3 h-3" />
                        </button>
                    </li>
                </ul>
                <p class="mt-1.5 opacity-80">Drag a file onto a row's PDF/XLSX cell above to attach it, or remove it.</p>
            </div>

            <div v-if="attachments.length" class="flex items-center justify-between gap-2 text-xs">
                <span class="text-[var(--color-text-muted)]">{{ attachments.length }} attachment(s) selected, {{ formatSize(attachments.reduce((sum, f) => sum + f.size, 0)) }} total</span>
                <Button type="button" variant="ghost" size="sm" class="cursor-pointer h-auto py-0.5" @click="clearAttachments">Clear all</Button>
            </div>
        </div>

        <!-- Step 3: Review and submit -->
        <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium">{{ rowCount }} row{{ rowCount !== 1 ? 's' : '' }}</span>
                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 px-2 py-0.5 text-xs font-medium">
                    <CheckCircle2 class="w-3 h-3" /> {{ rowStatusCounts.ready }} ready
                </span>
                <span v-if="rowStatusCounts.missing" class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 px-2 py-0.5 text-xs font-medium">
                    <AlertTriangle class="w-3 h-3" /> {{ rowStatusCounts.missing }} missing file
                </span>
                <span v-if="rowStatusCounts.invalid" class="inline-flex items-center gap-1 rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 px-2 py-0.5 text-xs font-medium">
                    <XCircle class="w-3 h-3" /> {{ rowStatusCounts.invalid }} invalid
                </span>
                <span v-if="unmatchedFiles.length" class="ml-auto text-xs text-[var(--color-text-muted)]">
                    {{ unmatchedFiles.length }} unmatched file{{ unmatchedFiles.length !== 1 ? 's' : '' }} —
                    <button type="button" class="cursor-pointer underline" @click="currentStep = 2">review</button>
                </span>
            </div>

            <BatchUploadRowsTable
                :columns="columns"
                :attachment-columns="attachmentColumns"
                :items="pagedDisplayRows"
                :cell-is-wrong="cellIsWrong"
                @row-drop="onRowDrop"
                @row-file="onRowFile" />

            <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-[var(--color-text-muted)]">Showing {{ previewFrom }}–{{ previewTo }} of {{ rowCount }}</span>
                <div class="flex items-center gap-1">
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer" :disabled="previewPage <= 1" @click="goToPreviewPage(previewPage - 1)">
                        <ChevronLeft class="w-4 h-4" />
                    </Button>
                    <span class="text-xs text-[var(--color-text-muted)] px-1">Page {{ previewPage }} of {{ previewTotalPages }}</span>
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer" :disabled="previewPage >= previewTotalPages" @click="goToPreviewPage(previewPage + 1)">
                        <ChevronRight class="w-4 h-4" />
                    </Button>
                </div>
            </div>

            <div class="flex items-start gap-2 rounded-md border border-[var(--color-border)] px-3 py-2.5">
                <Checkbox id="skip_errors" v-model="skipErrors" class="cursor-pointer mt-0.5" />
                <div class="grid gap-0.5">
                    <Label for="skip_errors" class="cursor-pointer text-sm font-medium">Skip rows with errors</Label>
                    <p class="text-xs text-[var(--color-text-muted)]">
                        <template v-if="skipErrors">Ready rows are uploaded now; Missing/Invalid rows are skipped and listed for you to fix and retry.</template>
                        <template v-else>Every row must pass validation — if any row is Missing or Invalid, nothing is uploaded until it's fixed or this is checked.</template>
                    </p>
                </div>
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
                    <XCircle class="w-3.5 h-3.5" /> {{ result.failed }} {{ result.partial ? 'skipped' : 'rejected' }}
                </span>
                <span class="text-xs text-[var(--color-text-muted)]">of {{ result.total }} rows</span>
                <span v-if="result.failed > 0 && !result.partial" class="text-xs text-[var(--color-text-muted)]">
                    Nothing was saved — fix the rows below and upload again, or check "Skip rows with errors".
                </span>
            </div>

            <div v-if="allIssues.length" class="space-y-2">
                <p class="text-sm font-medium text-[var(--color-text)]">
                    {{ allIssues.length }} row{{ allIssues.length !== 1 ? 's' : '' }} need attention
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
                    with the same treatment.
                </p>
            </div>
        </div>

        <!-- Wizard navigation -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--color-border)] pt-3">
            <Button v-if="currentStep > 1" type="button" variant="outline" class="cursor-pointer" :disabled="submitting" @click="goBack">
                <ChevronLeft class="w-4 h-4 mr-1" /> Back
            </Button>
            <span v-else />

            <div class="flex flex-wrap items-center justify-end gap-2">
                <div v-if="chunkProgress" class="mr-auto flex items-center gap-2 text-xs text-[var(--color-text-muted)]">
                    <div class="h-1.5 w-32 overflow-hidden rounded-full bg-[var(--color-border)]">
                        <div
                            class="h-full rounded-full bg-[var(--primary-color)] transition-all"
                            :style="{ width: `${Math.round((chunkProgress.current / chunkProgress.total) * 100)}%` }" />
                    </div>
                    <span>Batch {{ chunkProgress.current }} of {{ chunkProgress.total }}</span>
                </div>
                <p
                    v-else-if="currentStep === 3 && submitBlockedReason && rowCount > 0"
                    class="mr-auto flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                    <AlertTriangle class="w-3.5 h-3.5 shrink-0" />
                    {{ submitBlockedReason }}
                </p>
                <Button type="button" variant="outline" class="cursor-pointer" :disabled="submitting" @click="onCancel">
                    Cancel
                </Button>
                <Button v-if="currentStep < 3" type="button" class="cursor-pointer" :disabled="currentStep === 1 && !canProceedToAttachments" @click="goNext">
                    Next <ChevronRight class="w-4 h-4 ml-1" />
                </Button>
                <Button v-else type="button" class="cursor-pointer" :disabled="!canSubmit" @click="submit">
                    <UploadCloud class="w-4 h-4 mr-1" /> {{ submitLabel }}
                </Button>
            </div>
        </div>
    </div>
</template>
