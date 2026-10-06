<script setup lang="ts">
/**
 * A list's search box: a search icon, a clear button, and Esc to clear.
 *
 * Only holds the text — when to reload is the list's decision, since each list debounces
 * against its own other filters.
 */
import type { HTMLAttributes } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Search, X } from 'lucide-vue-next';

const props = defineProps<{
  id: string;
  /** What is searched, for screen readers — the placeholder is not a label. */
  label: string;
  placeholder?: string;
  class?: HTMLAttributes['class'];
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
  <div :class="cn('relative w-full sm:max-w-sm', props.class)">
    <Search
      class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
      aria-hidden="true" />
    <Input
      :id="id"
      v-model="model"
      type="text"
      autocomplete="off"
      :aria-label="label"
      :placeholder="placeholder"
      class="h-9 pr-8 pl-8"
      @keydown.esc="model = ''" />
    <button
      v-if="model"
      type="button"
      class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer rounded-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
      aria-label="Clear search"
      @click="model = ''">
      <X class="size-4" />
    </button>
  </div>
</template>
