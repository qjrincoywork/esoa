<script setup lang="ts">
import type { Component } from 'vue';
import { useId } from 'vue';

/**
 * A titled group of dashboard widgets.
 *
 * The page answers its questions in reading order — how much is owed, how old it is, how
 * billing is moving, who holds it — and a heading per group lets a reader jump to the one
 * they came for instead of scanning a wall of equal cards. Headings stay small and quiet:
 * they label the groups, the cards are still the content.
 */
defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();

const headingId = useId();
</script>

<template>
    <section :aria-labelledby="headingId" class="flex flex-col gap-3">
        <div class="flex items-start gap-2">
            <component
                :is="icon"
                v-if="icon"
                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h2
                    :id="headingId"
                    class="text-sm leading-tight font-semibold text-foreground"
                >
                    {{ title }}
                </h2>
                <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>

        <slot />
    </section>
</template>
