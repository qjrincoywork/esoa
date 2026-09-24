<script setup lang="ts">
/**
 * Presentational rows table shared by the "Attach files" and "Review and submit" steps
 * of the batch upload wizard — one place for the per-row status highlighting and the
 * attachment drop targets, so the two steps can never drift out of sync with each other.
 *
 * Status is computed once per row by the parent (which owns the validation state) and
 * handed down as part of each item, rather than recomputed per cell here.
 */
import { ref } from 'vue';
import { CheckCircle2, AlertTriangle, XCircle, Paperclip } from 'lucide-vue-next';

export type RowStatusKind = 'ready' | 'missing' | 'invalid';
export interface RowStatus {
    kind: RowStatusKind;
    label: string;
}
export interface DisplayRow {
    row: Record<string, string>;
    line: number;
    status: RowStatus;
}

const props = defineProps<{
    columns: string[];
    attachmentColumns: string[];
    items: DisplayRow[];
    cellIsWrong: (row: Record<string, string>, column: string) => boolean;
}>();

const emit = defineEmits<{
    'row-drop': [payload: { row: Record<string, string>; column: string; event: DragEvent }];
    'row-file': [payload: { row: Record<string, string>; column: string; file: File }];
}>();

const isAttachmentColumn = (column: string): boolean => props.attachmentColumns.includes(column);
const attachmentLabel = (column: string): string => (column.toLowerCase().includes('pdf') ? 'PDF' : 'XLSX');
const acceptFor = (column: string): string => (attachmentLabel(column) === 'PDF' ? '.pdf' : '.xls,.xlsx');

/** Which attachment cell is being dragged over, so only that one cell highlights. */
const dragOverKey = ref<string | null>(null);
const cellKey = (line: number, column: string): string => `${line}:${column}`;

const onDrop = (item: DisplayRow, column: string, event: DragEvent): void => {
    dragOverKey.value = null;
    emit('row-drop', { row: item.row, column, event });
};

const onFileInput = (item: DisplayRow, column: string, event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (file) emit('row-file', { row: item.row, column, file });
};

const rowClass = (status: RowStatus): string => {
    if (status.kind === 'invalid') return 'bg-red-50 dark:bg-red-900/10';
    if (status.kind === 'missing') return 'bg-amber-50 dark:bg-amber-900/10';
    return '';
};

const statusBadgeClass = (status: RowStatus): string => {
    if (status.kind === 'invalid') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    if (status.kind === 'missing') return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
    return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
};
</script>

<template>
    <div class="border border-[var(--color-border)] rounded-md overflow-auto max-h-96">
        <table class="w-full text-xs">
            <thead class="sticky top-0 bg-[var(--color-surface)] z-10">
                <tr>
                    <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">#</th>
                    <th
                        v-for="col in columns"
                        :key="col"
                        class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium whitespace-nowrap">
                        {{ col }}<span v-if="isAttachmentColumn(col)" class="font-normal text-[var(--color-text-muted)]">
                            ({{ attachmentLabel(col) === 'PDF' ? 'required' : 'optional' }})</span>
                    </th>
                    <th class="border-b border-[var(--color-border)] px-2 py-1.5 text-left font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in items" :key="item.line" :class="rowClass(item.status)">
                    <td class="border-b border-[var(--color-border)] px-2 py-1 text-[var(--color-text-muted)] align-top">
                        {{ item.line }}
                    </td>
                    <td
                        v-for="col in columns"
                        :key="col"
                        class="border-b border-[var(--color-border)] px-2 py-1 align-top whitespace-nowrap">
                        <template v-if="isAttachmentColumn(col)">
                            <span
                                v-if="item.row[col]"
                                class="inline-flex items-center gap-1 rounded-full border border-[var(--color-border)] px-1.5 py-0.5">
                                <Paperclip class="w-3 h-3 shrink-0" /> {{ item.row[col] }}
                            </span>
                            <label
                                v-else
                                class="inline-flex items-center gap-1 rounded-md border border-dashed px-2 py-1 cursor-pointer transition-colors"
                                :class="dragOverKey === cellKey(item.line, col)
                                    ? 'border-[var(--primary-color)] bg-[var(--color-surface-muted,transparent)]'
                                    : 'border-[var(--color-border-strong)] hover:bg-[var(--color-surface-muted,transparent)]'"
                                @dragover.prevent="dragOverKey = cellKey(item.line, col)"
                                @dragleave.prevent="dragOverKey = null"
                                @drop.prevent="onDrop(item, col, $event)">
                                + Attach {{ attachmentLabel(col) }}
                                <input
                                    type="file"
                                    class="hidden"
                                    :accept="acceptFor(col)"
                                    @change="onFileInput(item, col, $event)" />
                            </label>
                        </template>
                        <span
                            v-else
                            :class="cellIsWrong(item.row, col) ? 'text-red-600 dark:text-red-400 font-medium' : ''">
                            {{ item.row[col] || '—' }}
                        </span>
                    </td>
                    <td class="border-b border-[var(--color-border)] px-2 py-1 align-top whitespace-nowrap">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="statusBadgeClass(item.status)">
                            <CheckCircle2 v-if="item.status.kind === 'ready'" class="w-3 h-3" />
                            <AlertTriangle v-else-if="item.status.kind === 'missing'" class="w-3 h-3" />
                            <XCircle v-else class="w-3 h-3" />
                            {{ item.status.label }}
                        </span>
                    </td>
                </tr>
                <tr v-if="items.length === 0">
                    <td :colspan="columns.length + 2" class="px-2 py-4 text-center text-[var(--color-text-muted)]">
                        No rows to show.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
