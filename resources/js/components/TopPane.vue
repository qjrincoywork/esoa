<script setup lang="ts">
import type { Component } from 'vue';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/components/ui/sheet';

type SheetSide = 'top' | 'right' | 'bottom' | 'left';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        side?: SheetSide;
        contentComponent?: Component | null;
        componentProps?: Record<string, any>;
        loading?: boolean;
        error?: string | null;
    }>(),
    {
        title: '',
        side: 'top',
        contentComponent: null,
        componentProps: () => ({}),
        loading: false,
        error: null,
    }
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>()
</script>

<template>
    <Sheet :open="props.open" @update:open="emit('update:open', $event)">
        <SheetContent :side="props.side" class="p-0">
            <div class="flex h-full w-full flex-col">
                <div
                    class="flex items-center justify-between gap-3 border-b border-[var(--color-border)] px-4 py-3">
                    <SheetTitle class="text-base">
                        {{ props.title }}
                    </SheetTitle>
                </div>
                <SheetDescription class="sr-only">
                    {{ props.title || 'Pane' }} content.
                </SheetDescription>

                <div class="flex-1 overflow-y-auto p-4">
                    <div
                        v-if="props.error"
                        class="rounded-md border border-red-500/30 bg-red-50/30 px-3 py-2 text-sm text-red-700 dark:text-red-300">
                        {{ props.error }}
                    </div>
                    <div
                        v-if="props.loading"
                        class="flex items-center justify-center py-10 text-sm text-[var(--color-text-muted)]">
                        Loading...
                    </div>

                    <component
                        v-else-if="props.contentComponent"
                        :is="props.contentComponent"
                        v-bind="props.componentProps" />

                    <div
                        v-else
                        class="text-sm text-[var(--color-text-muted)]">
                        No content
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
