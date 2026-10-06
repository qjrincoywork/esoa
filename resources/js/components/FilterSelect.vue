<script setup lang="ts">
/**
 * One dropdown in a list's filter bar, bound to a plain string where '' means "no filter".
 *
 * A Select cannot hold an empty value, so an "all" sentinel stands in for it — here and
 * nowhere else, so no page has to translate it back. While a filter is set its trigger
 * is tinted, so what is narrowing the list can be seen without opening anything.
 */
import type { HTMLAttributes } from 'vue';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

const props = defineProps<{
  /** What the filter narrows by — named to screen readers and in the hover title. */
  label: string;
  /** How "no filter" reads, e.g. "All types". */
  allLabel: string;
  options: { value: string; label: string }[];
  class?: HTMLAttributes['class'];
}>();

const model = defineModel<string>({ required: true });

const ALL = '__all__';

const select = (value: unknown) => {
  model.value = value == null || value === ALL ? '' : String(value);
};
</script>

<template>
  <Select :model-value="model || ALL" @update:model-value="select">
    <SelectTrigger
      :class="cn('h-9 text-sm', model && 'border-primary/40 bg-primary/5 font-medium dark:bg-primary/10', props.class)"
      :aria-label="label"
      :title="label">
      <SelectValue />
    </SelectTrigger>
    <SelectContent>
      <SelectGroup>
        <SelectItem :value="ALL" class="text-muted-foreground">{{ allLabel }}</SelectItem>
        <SelectItem v-for="option in options" :key="option.value" :value="option.value">
          {{ option.label }}
        </SelectItem>
      </SelectGroup>
    </SelectContent>
  </Select>
</template>
