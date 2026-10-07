<script setup lang="ts">
/**
 * "How to fill in the spreadsheet" — the batch upload's help panel, written for the
 * people who fill the sheet in rather than the people who built the importer.
 *
 * Everything it lists comes from the batch_create metadata (the same lists the
 * importer validates against), so a column, code or limit changed server-side is
 * reflected here without touching the copy. Only the plain-language description of
 * each column lives here; a column this map does not know still appears, described
 * by its humanised name.
 */
import { computed } from 'vue';
import {
    CalendarDays,
    CheckCircle2,
    CircleHelp,
    Hash,
    ListChecks,
    Paperclip,
    ShieldCheck,
    XCircle,
} from 'lucide-vue-next';

type Option = { value: string | number; name: string };

const props = defineProps<{
    columns: string[];
    requiredColumns: string[];
    attachmentColumns: string[];
    dateColumns: string[];
    accountTypes: Option[];
    billTypes: Option[];
    statusTypes: Option[];
    maxRows: number;
    /** An SOA number from the uploaded sheet, so examples use the user's own data. */
    sampleSoaNumber?: string;
}>();

/** What to type in each column, in everyday words. */
const DESCRIPTIONS: Record<string, string> = {
    soa_number: 'The billing invoice number. Letters, numbers and dashes only.',
    account_type: 'Filled in for you from the account code — leave it blank unless you want it double-checked.',
    account_code: "The client's account code.",
    branch_code: 'The branch code, only if the invoice is for a single branch.',
    bill_type: 'A number from the “Bill type” list below.',
    status: 'A number from the “Status” list below.',
    due_date: 'The date payment is due.',
    period_date_from: 'The first day the invoice covers.',
    period_date_to: 'The last day the invoice covers.',
    contract_date_from: 'The contract start date.',
    contract_date_to: 'The contract end date.',
    billing_date: 'The date the invoice was issued.',
    amount: 'The invoice total as a plain number, e.g. 15000.50 — no ₱ sign and no commas.',
    file_pdf: 'The invoice PDF. See “Attaching the files” below — it can often be left blank.',
    file_xls: 'The invoice Excel file. See “Attaching the files” below.',
};

const humanise = (column: string) => column.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

const sample = computed(() => props.sampleSoaNumber || 'BI-0001234567');

/**
 * One row per column, in template order. An attachment column is never shown as
 * strictly required: its cell may be left blank whenever the file is named after the
 * SOA number, which the uploader should hear before they start typing file names.
 */
const columnGuide = computed(() =>
    props.columns.map((column) => {
        const isAttachment = props.attachmentColumns.includes(column);
        const isRequired = props.requiredColumns.includes(column);

        return {
            column,
            description: DESCRIPTIONS[column] ?? `${humanise(column)}.`,
            isDate: props.dateColumns.includes(column),
            need: isAttachment ? 'see-files' : isRequired ? 'required' : 'optional',
        } as const;
    }),
);

const NEED_BADGE = {
    required: { label: 'Required', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    optional: { label: 'Optional', class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' },
    'see-files': { label: 'See below', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' },
} as const;

/**
 * Codes in the order someone looks them up — numerically — whatever order the server
 * lists them in for its own dropdowns. Letter codes keep their given order.
 */
const byCode = (options: Option[]): Option[] =>
    options.every((option) => Number.isFinite(Number(option.value)))
        ? [...options].sort((a, b) => Number(a.value) - Number(b.value))
        : options;

/** The code lists, as small "type this → it means" tables. Account type is last: it is optional. */
const codeLists = computed(() => [
    { title: 'Status', column: 'status', note: null, options: byCode(props.statusTypes) },
    { title: 'Bill type', column: 'bill_type', note: null, options: byCode(props.billTypes) },
    { title: 'Account type', column: 'account_type', note: 'Optional — normally filled in for you.', options: byCode(props.accountTypes) },
].filter((list) => list.options.length > 0));

/** The bill type that needs no Excel file — looked up by name so the code is never hard-coded. */
const ecuBillType = computed(() => props.billTypes.find((type) => /\becu\b/i.test(type.name)) ?? null);

const pdfColumn = computed(() => props.attachmentColumns.find((c) => c.includes('pdf')) ?? 'file_pdf');
const xlsColumn = computed(() => props.attachmentColumns.find((c) => !c.includes('pdf')) ?? 'file_xls');
</script>

<template>
    <details class="group rounded-md border border-[var(--color-border)] text-sm">
        <summary class="flex cursor-pointer items-center gap-2 px-3 py-2.5 select-none">
            <CircleHelp class="h-4 w-4 shrink-0 text-[var(--primary-color)]" />
            <span class="font-medium text-[var(--color-text)]">How to fill in the spreadsheet</span>
            <span class="text-xs text-[var(--color-text-muted)]">— what goes in each column, the codes to use, and how files are attached</span>
            <span class="ml-auto text-xs text-[var(--color-text-muted)] group-open:hidden">Show</span>
            <span class="ml-auto hidden text-xs text-[var(--color-text-muted)] group-open:inline">Hide</span>
        </summary>

        <div class="space-y-5 border-t border-[var(--color-border)] px-3 py-4">
            <p class="text-[var(--color-text-muted)]">
                Each row of the spreadsheet is <strong class="text-[var(--color-text)]">one billing invoice</strong>.
                Keep the column names in the first row exactly as they are in the template.
            </p>

            <!-- 1. Columns -->
            <section class="space-y-2">
                <h4 class="flex items-center gap-1.5 font-medium text-[var(--color-text)]">
                    <ListChecks class="h-4 w-4" /> What goes in each column
                </h4>
                <div class="overflow-auto rounded-md border border-[var(--color-border)] max-h-72">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-[var(--color-surface)]">
                            <tr class="text-left text-[var(--color-text-muted)]">
                                <th class="border-b border-[var(--color-border)] px-2 py-1.5 font-medium">Column</th>
                                <th class="border-b border-[var(--color-border)] px-2 py-1.5 font-medium">What to enter</th>
                                <th class="border-b border-[var(--color-border)] px-2 py-1.5 font-medium">Needed?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in columnGuide" :key="item.column" class="align-top">
                                <td class="border-b border-[var(--color-border)] px-2 py-1.5 whitespace-nowrap">
                                    <code class="rounded bg-[var(--color-surface-muted,rgba(0,0,0,0.04))] px-1 py-0.5">{{ item.column }}</code>
                                </td>
                                <td class="border-b border-[var(--color-border)] px-2 py-1.5 text-[var(--color-text)]">
                                    {{ item.description }}
                                    <span v-if="item.isDate" class="text-[var(--color-text-muted)]"> Written as a date — see “Dates”.</span>
                                </td>
                                <td class="border-b border-[var(--color-border)] px-2 py-1.5 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium" :class="NEED_BADGE[item.need].class">
                                        {{ NEED_BADGE[item.need].label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 2. Codes -->
            <section class="space-y-2">
                <h4 class="flex items-center gap-1.5 font-medium text-[var(--color-text)]">
                    <Hash class="h-4 w-4" /> Codes to use
                </h4>
                <p class="text-xs text-[var(--color-text-muted)]">
                    Some columns take a number instead of a word. Type the number from the left column.
                </p>
                <div class="grid items-start gap-3 md:grid-cols-3">
                    <div v-for="list in codeLists" :key="list.column" class="max-h-64 overflow-auto rounded-md border border-[var(--color-border)]">
                        <div class="sticky top-0 border-b border-[var(--color-border)] bg-[var(--color-surface)] px-2 py-1.5">
                            <p class="text-xs font-medium text-[var(--color-text)]">
                                {{ list.title }} <code class="font-normal text-[var(--color-text-muted)]">({{ list.column }})</code>
                            </p>
                            <p v-if="list.note" class="text-[11px] text-[var(--color-text-muted)]">{{ list.note }}</p>
                        </div>
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left text-[var(--color-text-muted)]">
                                    <th class="px-2 py-1 font-medium w-16">Type</th>
                                    <th class="px-2 py-1 font-medium">Means</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="option in list.options" :key="String(option.value)" class="border-t border-[var(--color-border)]">
                                    <td class="px-2 py-1 font-mono font-semibold text-[var(--color-text)]">{{ option.value }}</td>
                                    <td class="px-2 py-1 text-[var(--color-text)]">{{ option.name }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 3. Dates -->
            <section class="space-y-2">
                <h4 class="flex items-center gap-1.5 font-medium text-[var(--color-text)]">
                    <CalendarDays class="h-4 w-4" /> Dates
                </h4>
                <p class="text-xs text-[var(--color-text-muted)]">
                    Write dates as <strong class="text-[var(--color-text)]">year-month-day</strong>. The downloaded template is
                    already set up for this — just type the date.
                </p>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="inline-flex items-center gap-1 rounded-md bg-green-50 px-2 py-1 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                        <CheckCircle2 class="h-3.5 w-3.5" /> <code>2026-10-05</code> — 5 October 2026
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-1 text-red-700 dark:bg-red-900/20 dark:text-red-400">
                        <XCircle class="h-3.5 w-3.5" /> <code>05/10/2026</code> — could be read as 10 May instead
                    </span>
                </div>
            </section>

            <!-- 4. Attachments -->
            <section class="space-y-2">
                <h4 class="flex items-center gap-1.5 font-medium text-[var(--color-text)]">
                    <Paperclip class="h-4 w-4" /> Attaching the files
                </h4>
                <p class="text-xs text-[var(--color-text-muted)]">You'll add the actual files in the next step. Two ways to link them to their rows:</p>
                <ol class="space-y-2 text-xs">
                    <li class="flex gap-2">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[var(--primary-color)] text-[11px] font-semibold text-white">1</span>
                        <div class="text-[var(--color-text)]">
                            <p><strong>Easiest:</strong> name each file after its SOA number and leave <code>{{ pdfColumn }}</code> / <code>{{ xlsColumn }}</code> blank.</p>
                            <p class="text-[var(--color-text-muted)]">
                                e.g. <code>{{ sample }}.pdf</code> and <code>{{ sample }}.xlsx</code> are matched to the row with SOA number <code>{{ sample }}</code> automatically.
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-2">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[var(--primary-color)] text-[11px] font-semibold text-white">2</span>
                        <div class="text-[var(--color-text)]">
                            <p>Files named differently? Type the file name in the column, <strong>without</strong> the “.pdf” or “.xlsx” at the end.</p>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1 rounded-md bg-green-50 px-2 py-0.5 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                    <CheckCircle2 class="h-3.5 w-3.5" /> <code>March-Invoice</code>
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2 py-0.5 text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                    <XCircle class="h-3.5 w-3.5" /> <code>March-Invoice.pdf</code>
                                </span>
                            </div>
                        </div>
                    </li>
                </ol>
                <p class="text-xs text-[var(--color-text-muted)]">
                    Every invoice needs its PDF.
                    <template v-if="ecuBillType">
                        The Excel file is needed too, <strong class="text-[var(--color-text)]">except</strong> when the bill type is
                        {{ ecuBillType.name }} (code {{ ecuBillType.value }}).
                    </template>
                    <template v-else>The Excel file is needed for most bill types.</template>
                </p>
            </section>

            <!-- 5. Before uploading -->
            <section class="flex items-start gap-2 rounded-md bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0" />
                <div class="space-y-0.5">
                    <p>Up to <strong>{{ maxRows }}</strong> invoices per upload — split bigger lists into several files.</p>
                    <p>
                        Every row is checked before anything is saved. If any row has a problem, <strong>nothing is uploaded</strong>
                        — unless you tick “Skip rows with errors” in the last step, which uploads the good rows and lists the rest to fix.
                    </p>
                </div>
            </section>
        </div>
    </details>
</template>
