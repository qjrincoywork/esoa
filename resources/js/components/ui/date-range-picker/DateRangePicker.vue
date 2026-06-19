<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const props = defineProps<{
  id?: string;
  label?: string;
  required?: boolean;
  from: string;
  to: string;
  class?: HTMLAttributes['class'];
  fromId?: string;
  toId?: string;
  fromName?: string;
  toName?: string;
}>();

const emit = defineEmits<{
  (e: 'update:from', value: string): void;
  (e: 'update:to', value: string): void;
}>();

const fromValue = computed({
  get: () => props.from,
  set: (v) => emit('update:from', v),
});

const toValue = computed({
  get: () => props.to,
  set: (v) => emit('update:to', v),
});

const resolvedFromId = computed(() => props.fromId ?? (props.id ? `${props.id}-from` : undefined));
const resolvedToId = computed(() => props.toId ?? (props.id ? `${props.id}-to` : undefined));

const dateInputClass =
  'flex-1 min-w-0 bg-background dark:bg-input/30 text-base outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm [color-scheme:light] dark:[color-scheme:dark]';
</script>

<template>
  <div :class="cn('grid gap-2', props.class)">
    <Label v-if="label" :for="resolvedFromId">
      {{ label }}<span v-if="required" class="text-red-400">*</span>
    </Label>
    <div
      class="border-input flex h-9 w-full items-center rounded-md border bg-background dark:bg-input/30 px-3 shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px]"
    >
      <input
        v-model="fromValue"
        type="date"
        :id="resolvedFromId"
        :name="fromName"
        :max="to || undefined"
        :class="dateInputClass"
      />
      <span class="text-muted-foreground shrink-0 select-none px-1.5">–</span>
      <input
        v-model="toValue"
        type="date"
        :id="resolvedToId"
        :name="toName"
        :min="from || undefined"
        :class="dateInputClass"
      />
    </div>
  </div>
</template>
