<script setup lang="ts" generic="S extends Record<string, any>, T extends Record<string, any> = S">
/**
 * Reusable two-panel drag-and-drop transfer list.
 *
 * Renders an "available" panel and an "assigned" panel and moves items between them,
 * by drag or by click — dragging is the fast path, the buttons and keyboard handlers
 * are the accessible one, and both raise the same events. The component owns no data:
 * it renders the arrays it is given and reports intent through `add` / `remove` /
 * `reorder`, leaving the owner to decide what a transfer actually means.
 *
 * The two sides are typed separately (`S` available, `T` assigned) because what is
 * offered is often not shaped like what is kept — a directory row on the left, a stored
 * record on the right — and defaulting `T` to `S` keeps the simple same-shape case
 * free of ceremony.
 *
 * Items are identified by `itemKey` (a property name or a resolver), which is how an
 * already-assigned item is recognised in the available panel and how reordering keeps
 * rows stable. Every row's appearance comes from the `source-item` / `target-item`
 * slots, so the same component serves any item shape; the panel headers take slots too
 * for search inputs, filters and load-more controls.
 */
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, ArrowRight, Check, GripVertical, Inbox, X } from 'lucide-vue-next';

type PanelSide = 'source' | 'target';
type KeyResolver = string | ((item: S | T) => string | number);

const props = withDefaults(
  defineProps<{
    /** Items offered for assignment (the left panel). */
    source: S[];
    /** Items currently assigned (the right panel). */
    target: T[];
    /** Property name, or resolver, giving each item its identity. */
    itemKey?: KeyResolver;
    sourceTitle?: string;
    targetTitle?: string;
    sourceHint?: string;
    targetHint?: string;
    sourceEmpty?: string;
    targetEmpty?: string;
    sourceLoading?: boolean;
    targetLoading?: boolean;
    /** Blocks every transfer while true (read-only rendering). */
    disabled?: boolean;
    /** Allows dragging assigned rows against each other to reorder them. */
    reorderable?: boolean;
    /** Cap on assigned items; null for no cap. */
    max?: number | null;
    /** Height class for both scroll areas, so panels line up with the pane. */
    listClass?: string;
  }>(),
  {
    itemKey: 'key',
    sourceTitle: 'Available',
    targetTitle: 'Assigned',
    sourceHint: '',
    targetHint: '',
    sourceEmpty: 'Nothing to show yet.',
    targetEmpty: 'Drag items here to assign them.',
    sourceLoading: false,
    targetLoading: false,
    disabled: false,
    reorderable: false,
    max: null,
    listClass: 'h-72',
  },
);

const emit = defineEmits<{
  add: [item: S];
  remove: [item: T, index: number];
  reorder: [items: T[]];
}>();

/** Which row is in flight, so a drop knows where it came from. */
const dragging = ref<{ from: PanelSide; index: number } | null>(null);
/** Panel currently under the pointer, for the drop-target outline. */
const hoveredPanel = ref<PanelSide | null>(null);
/** Insertion point while reordering, as an index into `target`. */
const dropIndex = ref<number | null>(null);

const resolveKey = (item: S | T): string => {
  const resolver = props.itemKey;

  return String(
    (typeof resolver === 'function' ? resolver(item) : item?.[resolver]) ?? '',
  );
};

/** Keys already assigned — a set so a long available list costs one lookup per row. */
const assignedKeys = computed(() => new Set(props.target.map((item) => resolveKey(item))));

const isAssigned = (item: S): boolean => assignedKeys.value.has(resolveKey(item));

const isFull = computed(() => props.max !== null && props.target.length >= props.max);

/** An item can be taken only while there is room and it is not already assigned. */
const canAdd = (item: S): boolean => !props.disabled && !isFull.value && !isAssigned(item);

const canRemove = computed(() => !props.disabled);

const resetDragState = () => {
  dragging.value = null;
  hoveredPanel.value = null;
  dropIndex.value = null;
};

const add = (item: S) => {
  if (!canAdd(item)) return;
  emit('add', item);
};

const remove = (index: number) => {
  const item = props.target[index];
  if (!canRemove.value || !item) return;
  emit('remove', item, index);
};

const onDragStart = (event: DragEvent, from: PanelSide, index: number) => {
  const item = from === 'source' ? props.source[index] : props.target[index];

  // Nothing to gain from starting a drag that could never be dropped.
  if (props.disabled || !item || (from === 'source' && !canAdd(props.source[index]))) {
    event.preventDefault();
    return;
  }

  dragging.value = { from, index };

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move';
    // Firefox refuses to start a drag until some payload is set.
    event.dataTransfer.setData('text/plain', resolveKey(item));
  }
};

const onPanelDragOver = (event: DragEvent, side: PanelSide) => {
  const drag = dragging.value;
  if (!drag) return;

  const isTransfer = drag.from !== side;
  const isReorder = drag.from === side && side === 'target' && props.reorderable;

  if (!isTransfer && !isReorder) return;
  if (isTransfer && side === 'target' && !canAdd(props.source[drag.index])) return;

  // Preventing the default is what marks this element as a valid drop target.
  event.preventDefault();
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
  hoveredPanel.value = side;
};

/** Track the insertion point while a reorder drag passes over an assigned row. */
const onTargetItemDragOver = (event: DragEvent, index: number) => {
  const drag = dragging.value;
  if (!props.reorderable || drag?.from !== 'target') return;

  const bounds = (event.currentTarget as HTMLElement).getBoundingClientRect();
  const isPastMidpoint = event.clientY > bounds.top + bounds.height / 2;

  dropIndex.value = isPastMidpoint ? index + 1 : index;
};

const onTargetDrop = (event: DragEvent) => {
  event.preventDefault();
  const drag = dragging.value;
  if (!drag) return resetDragState();

  if (drag.from === 'source') {
    add(props.source[drag.index]);
    return resetDragState();
  }

  if (props.reorderable) {
    const to = dropIndex.value ?? props.target.length;
    const reordered = [...props.target];
    const [moved] = reordered.splice(drag.index, 1);

    if (moved) {
      // Removing the row first shifts every later insertion point down by one.
      reordered.splice(to > drag.index ? to - 1 : to, 0, moved);
      if (reordered.some((item, index) => item !== props.target[index])) {
        emit('reorder', reordered);
      }
    }
  }

  resetDragState();
};

const onSourceDrop = (event: DragEvent) => {
  event.preventDefault();
  const drag = dragging.value;

  // Dropping an assigned row back onto the available panel unassigns it.
  if (drag?.from === 'target') remove(drag.index);

  resetDragState();
};

/**
 * Clear the drop outline only when the pointer actually leaves the panel — moving
 * between the rows inside it also fires dragleave, which would flicker the highlight.
 */
const onPanelDragLeave = (event: DragEvent) => {
  const panel = event.currentTarget as HTMLElement;
  const movedTo = event.relatedTarget as Node | null;

  if (!movedTo || !panel.contains(movedTo)) hoveredPanel.value = null;
};

/** Enter/Space on a focused row performs the same transfer as a drag would. */
const onRowKeydown = (event: KeyboardEvent, side: PanelSide, index: number) => {
  if (event.key !== 'Enter' && event.key !== ' ') return;
  event.preventDefault();

  if (side === 'source') {
    add(props.source[index]);
    return;
  }

  remove(index);
};

const panelClass = (side: PanelSide) => [
  'flex min-w-0 flex-1 flex-col rounded-lg border transition-colors',
  hoveredPanel.value === side
    ? 'border-[var(--primary-color)] bg-[var(--primary-color)]/5'
    : 'border-[var(--color-border)]',
];

const rowClass = (interactive: boolean) => [
  'group flex items-start gap-2 rounded-md border px-2 py-1.5 text-sm transition-colors',
  'border-[var(--color-border)] bg-[var(--color-surface)]',
  interactive
    ? 'cursor-grab hover:border-[var(--primary-color)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary-color)]'
    : 'cursor-not-allowed opacity-60',
];
</script>

<template>
  <div class="flex flex-col gap-3 lg:flex-row">
    <!-- Available panel -->
    <section
      :class="panelClass('source')"
      @dragover="onPanelDragOver($event, 'source')"
      @dragleave="onPanelDragLeave"
      @drop="onSourceDrop">
      <header class="border-b border-[var(--color-border)] px-3 py-2">
        <div class="flex items-center justify-between gap-2">
          <h4 class="text-sm font-medium">{{ sourceTitle }}</h4>
          <span class="text-xs text-[var(--color-text-muted)]">{{ source.length }}</span>
        </div>
        <p v-if="sourceHint" class="mt-0.5 text-xs text-[var(--color-text-muted)]">
          {{ sourceHint }}
        </p>
        <slot name="source-toolbar" />
      </header>

      <div class="flex-1 overflow-y-auto p-2" :class="listClass">
        <p
          v-if="sourceLoading"
          class="py-6 text-center text-xs text-[var(--color-text-muted)]">
          Loading...
        </p>
        <ul v-else-if="source.length" class="flex flex-col gap-1.5">
          <li
            v-for="(item, index) in source"
            :key="resolveKey(item) || index"
            :class="rowClass(canAdd(item))"
            :draggable="canAdd(item)"
            :tabindex="canAdd(item) ? 0 : -1"
            :aria-disabled="!canAdd(item)"
            @dragstart="onDragStart($event, 'source', index)"
            @dragend="resetDragState"
            @keydown="onRowKeydown($event, 'source', index)"
            @dblclick="add(item)">
            <GripVertical
              v-if="canAdd(item)"
              class="mt-0.5 h-4 w-4 shrink-0 text-[var(--color-text-muted)]"
              aria-hidden="true" />
            <Check
              v-else-if="isAssigned(item)"
              class="mt-0.5 h-4 w-4 shrink-0 text-green-600"
              aria-hidden="true" />
            <div class="min-w-0 flex-1">
              <slot name="source-item" :item="item" :index="index" :assigned="isAssigned(item)">
                {{ resolveKey(item) }}
              </slot>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="h-6 shrink-0 px-1.5 opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100"
              :disabled="!canAdd(item)"
              :aria-label="`Assign ${resolveKey(item)}`"
              @click="add(item)">
              <ArrowRight class="h-3.5 w-3.5" />
            </Button>
          </li>
        </ul>
        <p v-else class="py-6 text-center text-xs text-[var(--color-text-muted)]">
          {{ sourceEmpty }}
        </p>
        <slot name="source-footer" />
      </div>
    </section>

    <!-- Assigned panel -->
    <section
      :class="panelClass('target')"
      @dragover="onPanelDragOver($event, 'target')"
      @dragleave="onPanelDragLeave"
      @drop="onTargetDrop">
      <header class="border-b border-[var(--color-border)] px-3 py-2">
        <div class="flex items-center justify-between gap-2">
          <h4 class="text-sm font-medium">{{ targetTitle }}</h4>
          <span class="text-xs text-[var(--color-text-muted)]">
            {{ target.length }}<template v-if="max !== null">/{{ max }}</template>
          </span>
        </div>
        <p v-if="targetHint" class="mt-0.5 text-xs text-[var(--color-text-muted)]">
          {{ targetHint }}
        </p>
        <slot name="target-toolbar" />
      </header>

      <div class="flex-1 overflow-y-auto p-2" :class="listClass">
        <p
          v-if="targetLoading"
          class="py-6 text-center text-xs text-[var(--color-text-muted)]">
          Loading...
        </p>
        <ul v-else-if="target.length" class="flex flex-col gap-1.5">
          <li
            v-for="(item, index) in target"
            :key="resolveKey(item) || index"
            :class="[
              rowClass(canRemove),
              dropIndex === index && dragging?.from === 'target'
                ? 'border-t-2 border-t-[var(--primary-color)]'
                : '',
            ]"
            :draggable="canRemove && reorderable"
            :tabindex="canRemove ? 0 : -1"
            @dragstart="onDragStart($event, 'target', index)"
            @dragover="onTargetItemDragOver($event, index)"
            @dragend="resetDragState"
            @keydown="onRowKeydown($event, 'target', index)"
            @dblclick="remove(index)">
            <GripVertical
              v-if="canRemove && reorderable"
              class="mt-0.5 h-4 w-4 shrink-0 text-[var(--color-text-muted)]"
              aria-hidden="true" />
            <div class="min-w-0 flex-1">
              <slot name="target-item" :item="item" :index="index">
                {{ resolveKey(item) }}
              </slot>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="h-6 shrink-0 px-1.5 text-red-500 hover:text-red-700"
              :disabled="!canRemove"
              :aria-label="`Unassign ${resolveKey(item)}`"
              @click="remove(index)">
              <X class="h-3.5 w-3.5" />
            </Button>
          </li>
        </ul>
        <div
          v-else
          class="flex flex-col items-center gap-2 py-8 text-center text-xs text-[var(--color-text-muted)]">
          <Inbox class="h-6 w-6" aria-hidden="true" />
          <span>{{ targetEmpty }}</span>
          <span class="inline-flex items-center gap-1">
            <ArrowLeft class="h-3 w-3" aria-hidden="true" />
            drag, double-click or press Enter on a row
          </span>
        </div>
        <slot name="target-footer" />
      </div>
    </section>
  </div>
</template>
