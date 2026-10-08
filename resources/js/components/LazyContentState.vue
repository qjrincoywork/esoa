<script setup lang="ts">
/**
 * The loading and failed states of content fetched on demand (`useLazyResource`).
 *
 * One look for "on its way" and "could not be loaded, try again" wherever a tab or panel
 * fetches when it is first shown, so each one only renders its own content.
 */
import { Button } from '@/components/ui/button';
import { Loader2, RotateCcw, TriangleAlert } from 'lucide-vue-next';

withDefaults(
  defineProps<{
    loading?: boolean;
    failed?: boolean;
    loadingText?: string;
    failedText?: string;
    /** A slim one-line notice above content already shown, instead of a centred block. */
    inline?: boolean;
  }>(),
  {
    loading: false,
    failed: false,
    loadingText: 'Loading…',
    failedText: 'This could not be loaded.',
    inline: false,
  },
);

const emit = defineEmits<{ retry: [] }>();
</script>

<template>
  <div
    v-if="loading || failed"
    :class="inline
      ? 'flex items-center gap-2 text-xs text-muted-foreground'
      : 'flex flex-col items-center justify-center gap-2 py-10 text-center text-sm text-muted-foreground'"
    :aria-busy="loading"
    aria-live="polite">
    <template v-if="loading">
      <Loader2 :class="inline ? 'size-3.5' : 'size-5'" class="animate-spin" aria-hidden="true" />
      <span>{{ loadingText }}</span>
    </template>
    <template v-else>
      <TriangleAlert :class="inline ? 'size-3.5' : 'size-5'" class="text-amber-500" aria-hidden="true" />
      <span>{{ failedText }}</span>
      <Button
        type="button"
        variant="outline"
        size="sm"
        :class="inline ? 'h-6 px-2 text-xs' : ''"
        class="cursor-pointer"
        @click="emit('retry')">
        <RotateCcw /> Try again
      </Button>
    </template>
  </div>
</template>
