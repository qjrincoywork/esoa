<script setup lang="ts">
/**
 * "Search multiple …" — look up many entries at once in a listing.
 *
 * Entries are typed or pasted into one box, separated by commas, semicolons or new
 * lines. The box only collects them: the host runs the lookup over its listing
 * ({@link useBulkLookup}), and the server splits, de-duplicates and caps the entries
 * (`AcceptsBulkSearchTerms`), then counts each one's matches under the listing's other
 * filters. Those counts come back as `matches` and are listed per entry — the entries
 * nothing matched stand out, and choosing one narrows the listing to that entry's
 * matches (`focus`).
 *
 * What is being looked up is the host's to say (title, description, nouns), so one panel
 * serves every listing that offers a bulk lookup.
 */
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BulkLookup, SearchTermMatch } from '@/composables/datatable/useBulkLookup';
import { cn } from '@/lib/utils';
import { ClipboardPaste, Search, X } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    /** Prefix for the panel's element ids, so two panels never share one. */
    id: string;
    title: string;
    description: string;
    placeholder?: string;
    /** What a row of the listing is called, for the per-entry hints: "user", "users". */
    noun: { one: string; many: string };
    /** Per-entry match counts of the lookup the listing is showing; empty before one runs. */
    matches?: SearchTermMatch[];
    /** Whether a lookup is applied to the listing (its results are shown while it is). */
    applied?: boolean;
    /** Most entries one lookup may hold — the server's `vc.max_search_terms`. */
    maxTerms?: number;
  }>(),
  {
    placeholder: '',
    matches: () => [],
    applied: false,
    maxTerms: 100,
  },
);

/** The entry the listing is narrowed to; null lists the matches of every entry. */
const focus = defineModel<string | null>('focus', { default: null });

const emit = defineEmits<{
  search: [lookup: BulkLookup];
  clear: [];
  close: [];
}>();

/** The separators `AcceptsBulkSearchTerms::SEARCH_TERM_SEPARATORS` splits on — previewed here. */
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

/** Toggle the listing between one entry's matches and every entry's. */
const toggleFocus = (term: string) => {
  focus.value = focus.value === term ? null : term;
};

const matchHint = (match: SearchTermMatch) =>
  match.count === 0
    ? `No ${props.noun.one} matches this entry`
    : `List the ${match.count} matching ${match.count === 1 ? props.noun.one : props.noun.many}`;

const chipClass = (match: SearchTermMatch) => cn(
  'inline-flex max-w-full items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs transition-colors',
  match.count === 0
    ? 'cursor-not-allowed border-red-300 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-900/20 dark:text-red-400'
    : focus.value === match.term
      ? 'cursor-pointer border-primary bg-primary/10 text-foreground'
      : 'cursor-pointer border-input text-foreground hover:border-primary',
);
</script>

<template>
  <div :id="id" class="rounded-lg border bg-card p-4" role="region" :aria-labelledby="`${id}-title`">
    <div class="flex items-start justify-between gap-2">
      <div>
        <h3 :id="`${id}-title`" class="text-sm font-semibold">{{ title }}</h3>
        <p class="mt-0.5 text-xs text-muted-foreground">{{ description }}</p>
      </div>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        class="size-7 shrink-0 cursor-pointer p-0 text-muted-foreground"
        :aria-label="`Close ${title.toLowerCase()}`"
        @click="emit('close')">
        <X class="size-4" />
      </Button>
    </div>

    <Label class="sr-only" :for="`${id}-input`">{{ description }}</Label>
    <Textarea
      :id="`${id}-input`"
      v-model="input"
      rows="4"
      :placeholder="placeholder"
      class="mt-3 resize-y"
      @keydown.ctrl.enter.prevent="search"
      @keydown.meta.enter.prevent="search" />

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <Button type="button" size="sm" class="cursor-pointer" :disabled="!canSearch" @click="search">
          <Search /> Search all
        </Button>
        <Button
          v-if="canReadClipboard"
          type="button"
          size="sm"
          variant="outline"
          class="cursor-pointer"
          @click="pasteFromClipboard">
          <ClipboardPaste /> Paste from clipboard
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
          :class="overLimit ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
          <template v-if="overLimit">{{ terms.length }} entries — search at most {{ maxTerms }} at a time</template>
          <template v-else>{{ terms.length }} {{ terms.length === 1 ? 'entry' : 'entries' }}</template>
        </span>
        <span v-if="clipboardBlocked" class="text-xs text-amber-600 dark:text-amber-400">
          Clipboard access was blocked — paste with Ctrl+V instead.
        </span>
      </div>

      <div class="flex items-center gap-2">
        <Checkbox :id="`${id}-exact`" v-model="exactMatch" class="cursor-pointer" />
        <Label :for="`${id}-exact`" class="cursor-pointer text-sm text-muted-foreground">
          Exact match only
        </Label>
      </div>
    </div>

    <!-- Per-entry results: what each entry matched, and which matched nothing -->
    <div v-if="applied && matches.length" class="mt-4 border-t pt-3">
      <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
        <p>
          <span class="font-medium text-foreground">{{ matchedCount }} of {{ matches.length }}</span>
          {{ matches.length === 1 ? 'entry' : 'entries' }} matched
          <template v-if="unmatchedCount">
            · <span class="text-red-600 dark:text-red-400">{{ unmatchedCount }} not found</span>
          </template>
        </p>
        <p>
          <template v-if="focus">
            Showing matches for <span class="font-medium text-foreground">{{ focus }}</span> ·
            <button type="button" class="cursor-pointer underline hover:text-foreground" @click="focus = null">
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
            :class="chipClass(match)"
            :disabled="match.count === 0"
            :aria-pressed="focus === match.term"
            :title="matchHint(match)"
            @click="toggleFocus(match.term)">
            <span class="max-w-[16rem] truncate">{{ match.term }}</span>
            <span class="font-semibold tabular-nums">{{ match.count === 0 ? 'No match' : match.count }}</span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
