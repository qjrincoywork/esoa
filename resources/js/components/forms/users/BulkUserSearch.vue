<script setup lang="ts">
/**
 * "Search multiple users" — look up many usernames or emails at once.
 *
 * Entries are typed or pasted into one box, separated by commas, semicolons or new
 * lines. The box only collects them: the host runs the lookup over the users list
 * (`search_terms`), and the server splits, de-duplicates and caps the entries
 * (`ListRequest`), then counts each one's matches under the list's other filters. Those
 * counts come back as `matches` and are listed per entry — the entries nobody matched
 * stand out, and choosing one narrows the list to that entry's matches (`focus`).
 */
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BulkUserLookup, SearchTermMatch } from '@/composables/users';
import { ClipboardPaste, Search, X } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    /** Per-entry match counts of the lookup the list is showing; empty before one runs. */
    matches?: SearchTermMatch[];
    /** Whether a lookup is applied to the list (its results are shown while it is). */
    applied?: boolean;
    /** Most entries one lookup may hold — the server's `vc.max_search_terms`. */
    maxTerms?: number;
  }>(),
  {
    matches: () => [],
    applied: false,
    maxTerms: 100,
  },
);

/** The entry the list is narrowed to; null lists the matches of every entry. */
const focus = defineModel<string | null>('focus', { default: null });

const emit = defineEmits<{
  search: [lookup: BulkUserLookup];
  clear: [];
  close: [];
}>();

/** The separators `ListRequest::SEARCH_TERM_SEPARATORS` splits on — previewed here. */
const SEPARATORS = /[,;\r\n]+/;

const input = ref('');
const exactMatch = ref(false);
const clipboardBlocked = ref(false);

/** The distinct entries typed so far, first spelling kept — what the server will search. */
const terms = computed(() => {
  const seen = new Set<string>();

  return input.value
    .split(SEPARATORS)
    .map((term) => term.trim())
    .filter((term) => {
      const key = term.toLowerCase();
      if (term === '' || seen.has(key)) return false;
      seen.add(key);

      return true;
    });
});

const overLimit = computed(() => terms.value.length > props.maxTerms);
const canSearch = computed(() => terms.value.length > 0 && !overLimit.value);

const matchedCount = computed(() => props.matches.filter((match) => match.count > 0).length);
const unmatchedCount = computed(() => props.matches.length - matchedCount.value);

const search = () => {
  if (!canSearch.value) return;
  emit('search', { terms: terms.value, exact: exactMatch.value });
};

// Switching match mode re-runs an applied lookup, so its counts never describe the other mode.
watch(exactMatch, () => {
  if (props.applied) search();
});

const clear = () => {
  input.value = '';
  clipboardBlocked.value = false;
  if (props.applied) emit('clear');
};

/** Clipboard reads need a secure context and the browser's permission; offer it only where possible. */
const canReadClipboard = typeof navigator !== 'undefined' && typeof navigator.clipboard?.readText === 'function';

/** Append the clipboard to what is already entered, so several lists can be pasted in turn. */
const pasteFromClipboard = async () => {
  try {
    const pasted = (await navigator.clipboard.readText()).trim();
    clipboardBlocked.value = false;
    if (pasted) input.value = [input.value.trim(), pasted].filter(Boolean).join('\n');
  } catch {
    clipboardBlocked.value = true;
  }
};

/** Toggle the list between one entry's matches and every entry's. */
const toggleFocus = (term: string) => {
  focus.value = focus.value === term ? null : term;
};

const chipClass = (match: SearchTermMatch) => {
  if (match.count === 0) {
    return 'cursor-not-allowed border-red-300 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-900/20 dark:text-red-400';
  }

  return focus.value === match.term
    ? 'cursor-pointer border-[var(--primary-color)] bg-[var(--primary-color)]/10 text-[var(--color-text)]'
    : 'cursor-pointer border-[var(--color-border-strong)] text-[var(--color-text)] hover:border-[var(--primary-color)]';
};
</script>

<template>
  <section
    class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4"
    aria-labelledby="bulk-user-search-title">
    <div class="flex items-start justify-between gap-2">
      <div>
        <h3 id="bulk-user-search-title" class="text-sm font-semibold text-[var(--color-text)]">
          Search multiple users
        </h3>
        <p class="mt-0.5 text-xs text-[var(--color-text-muted)]">
          Enter usernames or emails separated by commas, semicolons, or new lines.
        </p>
      </div>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        class="h-7 w-7 shrink-0 cursor-pointer p-0 text-[var(--color-text-muted)]"
        aria-label="Close bulk search"
        @click="emit('close')">
        <X class="h-4 w-4" />
      </Button>
    </div>

    <Label class="sr-only" for="bulk-user-search-input">Usernames or emails</Label>
    <Textarea
      id="bulk-user-search-input"
      v-model="input"
      rows="4"
      placeholder="jdelacruz, maria.santos@valucare.com.ph; jose.reyes"
      class="mt-3 resize-y border-[var(--color-border-strong)] bg-[var(--color-surface)] text-[var(--color-text)] focus-visible:ring-offset-0"
      :style="{ '--tw-ring-color': 'var(--primary-color)' }"
      @keydown.ctrl.enter.prevent="search"
      @keydown.meta.enter.prevent="search" />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <Button type="button" size="sm" class="cursor-pointer" :disabled="!canSearch" @click="search">
          <Search class="mr-1 h-4 w-4" /> Search all
        </Button>
        <Button
          v-if="canReadClipboard"
          type="button"
          size="sm"
          variant="outline"
          class="cursor-pointer"
          @click="pasteFromClipboard">
          <ClipboardPaste class="mr-1 h-4 w-4" /> Paste from clipboard
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          class="cursor-pointer"
          :disabled="!input && !applied"
          @click="clear">
          Clear
        </Button>
        <span
          v-if="terms.length"
          class="text-xs"
          :class="overLimit ? 'text-red-600 dark:text-red-400' : 'text-[var(--color-text-muted)]'">
          <template v-if="overLimit">{{ terms.length }} entries — search at most {{ maxTerms }} at a time</template>
          <template v-else>{{ terms.length }} {{ terms.length === 1 ? 'entry' : 'entries' }}</template>
        </span>
        <span v-if="clipboardBlocked" class="text-xs text-amber-600 dark:text-amber-400">
          Clipboard access was blocked — paste with Ctrl+V instead.
        </span>
      </div>

      <div class="flex items-center gap-2">
        <Checkbox id="bulk-user-search-exact" v-model="exactMatch" class="cursor-pointer" />
        <Label for="bulk-user-search-exact" class="cursor-pointer text-sm text-[var(--color-text-muted)]">
          Exact match only
        </Label>
      </div>
    </div>

    <!-- Per-entry results: what each entry matched, and which matched nobody -->
    <div v-if="applied && matches.length" class="mt-4 border-t border-[var(--color-border)] pt-3">
      <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-[var(--color-text-muted)]">
        <p>
          <span class="font-medium text-[var(--color-text)]">{{ matchedCount }} of {{ matches.length }}</span>
          {{ matches.length === 1 ? 'entry' : 'entries' }} matched
          <template v-if="unmatchedCount">
            · <span class="text-red-600 dark:text-red-400">{{ unmatchedCount }} not found</span>
          </template>
        </p>
        <p>
          <template v-if="focus">
            Showing matches for <span class="font-medium text-[var(--color-text)]">{{ focus }}</span> ·
            <button type="button" class="cursor-pointer underline hover:text-[var(--color-text)]" @click="focus = null">
              show all
            </button>
          </template>
          <template v-else>Select an entry to list only its matches</template>
        </p>
      </div>

      <ul class="mt-2 flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
        <li v-for="match in matches" :key="match.term">
          <button
            type="button"
            class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs transition-colors"
            :class="chipClass(match)"
            :disabled="match.count === 0"
            :aria-pressed="focus === match.term"
            :title="match.count === 0 ? 'No user matches this entry' : `List the ${match.count} matching user(s)`"
            @click="toggleFocus(match.term)">
            <span class="max-w-[16rem] truncate">{{ match.term }}</span>
            <span class="font-semibold tabular-nums">{{ match.count === 0 ? 'No match' : match.count }}</span>
          </button>
        </li>
      </ul>
    </div>
  </section>
</template>
