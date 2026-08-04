<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { FocusScope } from 'reka-ui';
import { GLOBAL_OVERLAY_ATTR } from '@/lib/overlay';

const props = defineProps({
    show: Boolean,
    size: { type: String, default: 'md' },
    closeOnClickOutside: { type: Boolean, default: true },
    modalTitle: { type: String, default: 'Modal Title' },
    modalButtonLabel: { type: String, default: 'Submit' },
});

const emit = defineEmits(['close', 'submit']);

const previousBodyOverflow = ref<string | null>(null);
const modalId = `modal-${Math.random().toString(36).slice(2, 11)}`;

/** Maps useModal sizes (2xl, 3xl, …) to Tailwind width tokens (xl2, xl3, …). */
const SIZE_ALIASES: Record<string, string> = {
  '2xl': 'xl2',
  '3xl': 'xl3',
  '4xl': 'xl4',
  '5xl': 'xl5',
};

const sizeClasses: Record<string, string> = {
  sm: 'w-full max-w-sm',
  md: 'w-full max-w-md',
  lg: 'w-full max-w-lg',
  xl: 'w-full max-w-xl',
  xl2: 'w-full max-w-xl2',
  xl3: 'w-full max-w-xl3',
  xl4: 'w-full max-w-xl4',
  xl5: 'w-full max-w-xl5',
  full: 'w-full max-w-[min(100%,96rem)]',
};

const resolvedSizeClass = computed(() => {
  const key = SIZE_ALIASES[props.size] ?? props.size ?? 'md';
  return sizeClasses[key] ?? sizeClasses.md;
});

/**
 * Escape closes this modal only.
 *
 * A Reka layer underneath listens for Escape on `window`; stopping propagation here — at
 * the document, one step earlier in the bubble path — keeps a single keypress from also
 * dismissing the pane the modal was opened from. Tab cycling is handled by the FocusScope
 * wrapping the panel.
 */
const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key !== 'Escape' || !props.show) return;

  e.stopPropagation();
  emit('close');
};

/** Bound to the backdrop, which sits behind the panel and never receives its clicks. */
const handleClickOutside = () => {
  if (props.closeOnClickOutside) emit('close');
};

/**
 * Lock body scroll, restoring the previous value rather than clearing it — an underlying
 * pane may already hold its own lock, which a blanket reset would silently release.
 */
const setBodyScrollLocked = (locked: boolean) => {
  if (locked) {
    if (previousBodyOverflow.value === null) {
      previousBodyOverflow.value = document.body.style.overflow;
    }
    document.body.style.overflow = 'hidden';
    return;
  }

  if (previousBodyOverflow.value !== null) {
    document.body.style.overflow = previousBodyOverflow.value;
    previousBodyOverflow.value = null;
  }
};

// Focus is handed to the panel — and handed back on close — by the FocusScope.
watch(() => props.show, setBodyScrollLocked, { immediate: true });

onMounted(() => document.addEventListener('keydown', handleKeyDown));
onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyDown);
  setBodyScrollLocked(false);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal" :duration="150">
      <div
        v-if="show"
        v-bind="{ [GLOBAL_OVERLAY_ATTR]: '' }"
        class="fixed inset-0 global-modal flex items-center justify-center p-2 sm:p-4 md:p-6"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="modalId">
        <div
          class="absolute inset-0 bg-black/30 dark:bg-black/50"
          aria-hidden="true"
          @click="handleClickOutside" />

        <!--
          Registering a FocusScope pauses the focus trap of any Reka layer below (the Sheet
          behind a pane), so the fields in here can actually take focus. It also owns Tab
          cycling and restores focus to the opener on close; unmounting resumes the layer
          underneath.
        -->
        <FocusScope as-child trapped loop>
          <article
            tabindex="-1"
            class="relative global-modal flex max-h-[calc(100dvh-1rem)] min-h-0 w-full flex-col overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/5 sm:max-h-[calc(100dvh-2rem)] md:max-h-[calc(100dvh-3rem)]"
            :class="resolvedSizeClass">
            <header
              class="flex flex-shrink-0 items-center justify-between border-b border-gray-200 px-3 py-3 dark:border-gray-700/50 sm:px-4 md:px-6 sm:py-4">
              <h2
                :id="modalId"
                class="pr-2 text-sm font-semibold text-gray-900 dark:text-white sm:text-base md:text-lg">
                <slot name="title" />
              </h2>
              <button
                type="button"
                class="flex-shrink-0 cursor-pointer rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-500 dark:hover:bg-gray-700/50 dark:hover:text-gray-400 dark:focus:ring-gray-700"
                aria-label="Close modal"
                @click="emit('close')">
                <svg
                  class="h-4 w-4 sm:h-5 sm:w-5"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </header>

            <section
              class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 dark:text-gray-200 sm:px-4 md:px-6 sm:py-4">
              <slot />
            </section>

            <footer
              v-if="$slots.footer"
              class="flex flex-shrink-0 justify-end gap-2 rounded-b-xl border-t border-gray-100 bg-gray-50 px-3 py-3 dark:border-gray-700/50 dark:bg-gray-800/50 sm:gap-3 sm:px-4 md:px-6 sm:py-4">
              <slot name="footer" />
            </footer>
          </article>
        </FocusScope>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.15s ease-out;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
