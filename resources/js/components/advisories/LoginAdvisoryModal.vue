<script setup lang="ts">
/**
 * The advisory pop-up shown on the first page after signing in.
 *
 * The server decides when: it sends `login_advisories` with exactly one page per
 * sign-in (App\Support\LoginAdvisories), so this component only has to show what it is
 * given — one advisory at a time when there are several — and remember the sign-in's
 * key so browser back/forward does not bring the same notice back.
 *
 * Clicking outside does not dismiss it: a notice like a maintenance window is meant to
 * be acknowledged, not lost to a stray click. The close button and Escape still work.
 */
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Megaphone } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import AdvisoryContent from '@/components/advisories/AdvisoryContent.vue';
import {
  hasSeenLoginAdvisories,
  markLoginAdvisoriesSeen,
  type LoginAdvisories,
  type LoginAdvisory,
} from '@/composables/advisories';

const page = usePage();

const open = ref(false);
const items = ref<LoginAdvisory[]>([]);
const index = ref(0);

const current = computed<LoginAdvisory | null>(() => items.value[index.value] ?? null);
const isLast = computed(() => index.value >= items.value.length - 1);
const hasMany = computed(() => items.value.length > 1);

watch(
  () => (page.props as { login_advisories?: LoginAdvisories | null }).login_advisories,
  (advisories) => {
    if (!advisories?.items?.length || hasSeenLoginAdvisories(advisories.key)) return;

    // Marked as soon as it is shown: the pop-up covers the page, so being shown is
    // being seen, and a replay of this page must not open it again.
    markLoginAdvisoriesSeen(advisories.key);
    items.value = advisories.items;
    index.value = 0;
    open.value = true;
  },
  { immediate: true },
);

const next = () => {
  if (isLast.value) {
    open.value = false;
  } else {
    index.value += 1;
  }
};

const previous = () => {
  if (index.value > 0) index.value -= 1;
};
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent
      v-if="current"
      class="max-h-[95vh] overflow-y-auto sm:max-w-xl"
      @interact-outside="(event: Event) => event.preventDefault()"
    >
      <DialogHeader class="pr-6">
        <div class="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
          <Megaphone class="size-4" />
          <span>Advisory</span>
          <span v-if="hasMany" class="tabular-nums normal-case">&middot; {{ index + 1 }} of {{ items.length }}</span>
        </div>
        <DialogTitle>{{ current.title }}</DialogTitle>
        <DialogDescription class="sr-only">
          An announcement for all users of the system.
        </DialogDescription>
      </DialogHeader>

      <AdvisoryContent
        :key="current.id"
        :title="current.title"
        :message="current.message"
        :image-url="current.image_url"
      />

      <DialogFooter>
        <Button v-if="hasMany && index > 0" variant="outline" class="cursor-pointer" @click="previous">
          Previous
        </Button>
        <Button class="cursor-pointer" @click="next">
          {{ isLast ? 'Got it' : 'Next' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
