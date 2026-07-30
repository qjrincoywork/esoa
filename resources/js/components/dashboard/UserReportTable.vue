<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { UserReportRow } from '@/types';
import { computed } from 'vue';

/**
 * Per-user activity report (staff roles only).
 *
 * Beyond seven or so classes a chart stops being the right form, so the full breakdown is
 * a table and only the leading users are charted. Selecting a row narrows the entire
 * dashboard to that user's records, which is why the row is a real button rather than a
 * click handler on the `tr`.
 */
const props = withDefaults(
    defineProps<{
        rows: UserReportRow[];
        activeUserId?: number | null;
        processing?: boolean;
    }>(),
    { activeUserId: null, processing: false },
);

const emit = defineEmits<{ select: [userId: number | null] }>();

const hasRows = computed(() => props.rows.length > 0);

const toggle = (row: UserReportRow) => {
    emit('select', props.activeUserId === row.user_id ? null : row.user_id);
};
</script>

<template>
    <div
        :class="
            cn(
                'transition-opacity duration-200',
                processing ? 'opacity-50' : '',
            )
        "
    >
        <p
            v-if="!hasRows"
            class="py-10 text-center text-sm text-muted-foreground"
        >
            No user activity recorded for the selected period.
        </p>

        <div v-else class="max-h-[26rem] overflow-auto">
            <table class="w-full min-w-[60rem] text-sm">
                <thead class="sticky top-0 z-10 bg-card">
                    <tr class="border-b text-xs text-muted-foreground">
                        <th scope="col" class="py-2 pr-3 text-left font-medium">
                            User
                        </th>
                        <th scope="col" class="py-2 pr-3 text-left font-medium">
                            Type
                        </th>
                        <th scope="col" class="py-2 pr-3 text-left font-medium">
                            Invoices from
                        </th>
                        <th
                            scope="col"
                            class="py-2 pr-3 text-right font-medium"
                        >
                            Invoices
                        </th>
                        <th
                            scope="col"
                            class="py-2 pr-3 text-right font-medium"
                        >
                            Billed
                        </th>
                        <th
                            scope="col"
                            class="py-2 pr-3 text-right font-medium"
                        >
                            Outstanding
                        </th>
                        <th
                            scope="col"
                            class="py-2 pr-3 text-right font-medium"
                        >
                            Concerns
                        </th>
                        <th
                            scope="col"
                            class="py-2 pr-3 text-right font-medium"
                        >
                            Payments
                        </th>
                        <th scope="col" class="py-2 text-left font-medium">
                            Last activity
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.key"
                        :class="[
                            'border-b last:border-0',
                            activeUserId === row.user_id
                                ? 'bg-muted/70'
                                : 'hover:bg-muted/40',
                        ]"
                    >
                        <td class="py-2 pr-3">
                            <button
                                type="button"
                                class="max-w-56 truncate rounded-md text-left font-medium text-foreground underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                :title="`${row.name} (${row.username})`"
                                :aria-pressed="activeUserId === row.user_id"
                                @click="toggle(row)"
                            >
                                {{ row.name }}
                            </button>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ row.email ?? row.username }}
                            </p>
                        </td>
                        <td class="py-2 pr-3">
                            <div class="flex flex-wrap items-center gap-1">
                                <span class="text-muted-foreground">{{
                                    row.type_label
                                }}</span>
                                <Badge
                                    v-if="activeUserId === row.user_id"
                                    variant="secondary"
                                    >Viewing</Badge
                                >
                                <Badge
                                    v-else-if="!row.is_active"
                                    variant="outline"
                                    >Inactive</Badge
                                >
                            </div>
                        </td>
                        <td class="py-2 pr-3">
                            <span
                                class="text-muted-foreground"
                                :title="row.scope_description"
                            >
                                {{ row.scope_label }}
                            </span>
                        </td>
                        <td class="py-2 pr-3 text-right tabular-nums">
                            {{ row.invoice_count }}
                        </td>
                        <td class="py-2 pr-3 text-right tabular-nums">
                            {{ row.billed_formatted }}
                        </td>
                        <td class="py-2 pr-3 text-right tabular-nums">
                            {{ row.outstanding_formatted }}
                        </td>
                        <td class="py-2 pr-3 text-right tabular-nums">
                            {{ row.concern_count }}
                        </td>
                        <td class="py-2 pr-3 text-right tabular-nums">
                            {{ row.payment_count }}
                        </td>
                        <td class="py-2 text-muted-foreground">
                            {{ row.last_activity_at ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
