<script setup lang="ts">
import * as XLSX from 'xlsx';
import { computed, ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { UploadCloud, Download, FileSpreadsheet, AlertTriangle, CheckCircle2, XCircle, ChevronLeft, ChevronRight } from 'lucide-vue-next';

interface ImportError {
    row: number;
    email: string | null;
    messages: string[];
}

interface ImportResult {
    total: number;
    created: number;
    failed: number;
    errors: ImportError[];
}

const props = defineProps<{
    columns: string[];
    requiredColumns: string[];
    types: { value: number | string; name: string }[];
    genders: string[];
    civilStatuses: string[];
    citizenships: string[];
    roles: string[];
    onReady: (api: {
        getPayload: () => Record<string, string>[] | null;
        setResult: (result: ImportResult) => void;
    }) => void;
}>();

const PREVIEW_PAGE_SIZE = 10;

const fileName = ref<string>('');
const parsing = ref(false);
const parseError = ref<string>('');
const rows = ref<Record<string, string>[]>([]);
const missingColumns = ref<string[]>([]);
const missingRequired = ref<string[]>([]);
const result = ref<ImportResult | null>(null);
const previewPage = ref(1);

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

/** Normalise a header/label into the canonical column key used by the backend. */
const canonical = (value: unknown): string =>
    String(value ?? '').toLowerCase().trim().replace(/\s+/g, '_');

const resetState = () => {
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
    resetState();
    parsing.value = true;

    try {
        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: 'array' });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const json = XLSX.utils.sheet_to_json<Record<string, unknown>>(sheet, { defval: '' });

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
            .map((raw) => {
                const obj: Record<string, string> = {};
                props.columns.forEach((column) => {
                    const rawHeader = headerMap.get(column);
                    const value = rawHeader !== undefined ? raw[rawHeader] : '';
                    obj[column] = value === null || value === undefined ? '' : String(value).trim();
                });
                return obj;
            })
            // Drop rows that are entirely blank (trailing spreadsheet rows).
            .filter((obj) => props.columns.some((c) => obj[c] !== ''));

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

const downloadTemplate = () => {
    const worksheet = XLSX.utils.aoa_to_sheet([props.columns]);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Users');
    XLSX.writeFile(workbook, 'user_import_template.xlsx');
};

const getPayload = (): Record<string, string>[] | null =>
    rows.value.length > 0 ? rows.value : null;

const setResult = (value: ImportResult) => {
    result.value = value;
};

onMounted(() => props.onReady({ getPayload, setResult }));
</script>

<template>
    <div class="space-y-4">
        <!-- Instructions + template -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-[var(--color-text-muted)]">
                Upload an Excel or CSV file. The first row must be the column headers.
            </p>
            <Button type="button" variant="outline" size="sm" class="cursor-pointer shrink-0" @click="downloadTemplate">
                <Download class="w-4 h-4 mr-1" /> Download template
            </Button>
        </div>

        <!-- File input -->
        <label
            class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-[var(--color-border-strong)] px-4 py-6 text-center cursor-pointer hover:bg-[var(--color-surface-muted,transparent)] transition-colors">
            <UploadCloud class="w-6 h-6 text-[var(--color-text-muted)]" />
            <span class="text-sm text-[var(--color-text)]">
                <span class="font-medium">Click to choose a file</span>
            </span>
            <span v-if="fileName" class="inline-flex items-center gap-1 text-xs text-[var(--color-text-muted)]">
                <FileSpreadsheet class="w-3.5 h-3.5" /> {{ fileName }}
            </span>
            <input type="file" class="hidden" accept=".xlsx,.xls,.csv" @change="onFileChange" />
        </label>

        <!-- Accepted values legend -->
        <details class="rounded-md border border-[var(--color-border)] px-3 py-2 text-xs">
            <summary class="cursor-pointer font-medium text-[var(--color-text)]">Accepted values &amp; rules</summary>
            <div class="mt-2 space-y-1.5 text-[var(--color-text-muted)]">
                <p><span class="font-medium text-[var(--color-text)]">Required columns:</span> {{ requiredColumns.join(', ') }}</p>
                <p><span class="font-medium text-[var(--color-text)]">username:</span> generated automatically from the email (the part before “@”); not a column.</p>
                <p><span class="font-medium text-[var(--color-text)]">type:</span> {{ types.map(t => t.name).join(', ') }} (name or number)</p>
                <p><span class="font-medium text-[var(--color-text)]">gender:</span> {{ genders.join(', ') }}</p>
                <p><span class="font-medium text-[var(--color-text)]">civil_status:</span> {{ civilStatuses.join(', ') }}</p>
                <p><span class="font-medium text-[var(--color-text)]">citizenship:</span> {{ citizenships.join(', ') }}</p>
                <p><span class="font-medium text-[var(--color-text)]">role:</span> {{ roles.join(', ') }} (optional)</p>
                <p><span class="font-medium text-[var(--color-text)]">account_code / branch_code:</span> each account_code is saved as-is; each branch_code is matched to its parent account automatically. Both accept comma-separated lists and both may be filled — every code becomes its own account row for the user. Required for Account/Branch Admin and Group Account Admin.</p>
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
            <span>Missing required column(s): <strong>{{ missingRequired.join(', ') }}</strong>. Every row will fail until these are added.</span>
        </div>
        <div
            v-else-if="missingColumns.length"
            class="flex items-start gap-1.5 rounded-md bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
            <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
            <span>Optional column(s) not found: <strong>{{ missingColumns.join(', ') }}</strong>. Those values will be left empty.</span>
        </div>

        <!-- Preview -->
        <div v-if="rowCount > 0 && !result" class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium text-[var(--color-text)]">
                    Preview — {{ rowCount }} row{{ rowCount !== 1 ? 's' : '' }} ready to import
                </p>
                <p class="text-xs text-[var(--color-text-muted)]">Username is generated automatically from each email.</p>
            </div>
            <div class="border border-[var(--color-border)] rounded-md overflow-auto max-h-72">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-[var(--color-surface)]">
                        <tr>
                            <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">#</th>
                            <th v-for="col in columns" :key="col" class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium whitespace-nowrap">
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in pagedPreviewRows" :key="i">
                            <td class="border-b border-[var(--color-border)] px-2 py-1 text-[var(--color-text-muted)]">{{ previewFrom + i }}</td>
                            <td v-for="col in columns" :key="col" class="border-b border-[var(--color-border)] px-2 py-1 whitespace-nowrap">
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
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer" :disabled="previewPage <= 1" @click="goToPreviewPage(previewPage - 1)">
                        <ChevronLeft class="w-4 h-4" />
                    </Button>
                    <span class="text-xs text-[var(--color-text-muted)] px-1">Page {{ previewPage }} of {{ previewTotalPages }}</span>
                    <Button type="button" variant="outline" size="sm" class="cursor-pointer" :disabled="previewPage >= previewTotalPages" @click="goToPreviewPage(previewPage + 1)">
                        <ChevronRight class="w-4 h-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Result summary -->
        <div v-if="result" class="space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 px-2.5 py-0.5 text-xs font-medium">
                    <CheckCircle2 class="w-3.5 h-3.5" /> {{ result.created }} created
                </span>
                <span
                    v-if="result.failed > 0"
                    class="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 px-2.5 py-0.5 text-xs font-medium">
                    <XCircle class="w-3.5 h-3.5" /> {{ result.failed }} failed
                </span>
                <span class="text-xs text-[var(--color-text-muted)]">of {{ result.total }} total</span>
            </div>

            <div v-if="result.errors.length" class="space-y-2">
                <p class="text-sm font-medium text-[var(--color-text)]">Rows that could not be imported</p>
                <div class="border border-[var(--color-border)] rounded-md overflow-auto max-h-72">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-[var(--color-surface)]">
                            <tr>
                                <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">Row</th>
                                <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">Email</th>
                                <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="err in result.errors" :key="err.row">
                                <td class="border-b border-[var(--color-border)] px-2 py-1 align-top">{{ err.row }}</td>
                                <td class="border-b border-[var(--color-border)] px-2 py-1 align-top whitespace-nowrap">{{ err.email || '—' }}</td>
                                <td class="border-b border-[var(--color-border)] px-2 py-1 align-top">
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        <li v-for="(msg, mi) in err.messages" :key="mi">{{ msg }}</li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-[var(--color-text-muted)]">Fix these rows in your file and upload again to import them.</p>
            </div>
        </div>
    </div>
</template>
