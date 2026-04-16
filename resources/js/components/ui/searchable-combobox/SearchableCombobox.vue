<script setup lang="ts">
import { CheckIcon, ChevronsUpDownIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { Label } from '@/components/ui/label';

/**
 * Item shape: { value, name } or { value, label }.
 * Display text is taken from name first, then label.
 */
export type SearchableComboboxItem = {
  value: string | number;
  name?: string;
  label?: string;
};

const props = withDefaults(
  defineProps<{
    /** List of options. Each item must have `value` and `name` (or `label`). */
    items: SearchableComboboxItem[];
    /** Selected value (v-model). */
    modelValue?: string | number | null;
    /** Search query (v-model:search). Parent can watch this for debounced API calls. */
    search?: string;
    /** Placeholder when nothing is selected. */
    placeholder?: string;
    /** Placeholder for the search input inside the popover. */
    searchPlaceholder?: string;
    /** Disable the trigger button. */
    disabled?: boolean;
    /** Text when items list is empty. */
    emptyText?: string;
    /** Whether there are more items to load (show "Load more" button). */
    hasMore?: boolean;
    /** Whether "Load more" request is in progress. */
    loadingMore?: boolean;
    /** Optional label text above the combobox (e.g. "Account"). */
    label?: string;
    /** Whether the label shows a required asterisk. */
    required?: boolean;
    /** Optional id for the trigger (used by label for attribute). */
    id?: string;
  }>(),
  {
    modelValue: null,
    search: '',
    placeholder: 'Select...',
    searchPlaceholder: 'Search...',
    disabled: false,
    emptyText: 'No results found.',
    hasMore: false,
    loadingMore: false,
    required: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null];
  'update:search': [value: string];
  loadMore: [];
}>();

const open = ref(false);

const searchProxy = computed({
  get: () => props.search ?? '',
  set: (val: string) => emit('update:search', val),
});

const selectedItem = computed(() =>
  props.items?.find((item) => String(item.value) === String(props.modelValue)),
);

const displayText = computed(() => {
  const item = selectedItem.value;
  if (!item) return props.placeholder;
  return (item.name ?? item.label ?? String(item.value)) as string;
});

function getItemDisplay(item: SearchableComboboxItem): string {
  return (item.name ?? item.label ?? String(item.value)) as string;
}

function onSelect(value: string) {
  emit('update:modelValue', value);
  open.value = false;
}

function onLoadMore() {
  emit('loadMore');
}
</script>

<template>
  <div class="grid gap-2 truncate">
    <Label v-if="label" :for="id">
      {{ label }}
      <span v-if="required" class="text-red-400">*</span>
    </Label>
    <Popover v-model:open="open">
      <PopoverTrigger as-child>
        <Button
          :id="id"
          variant="outline"
          role="combobox"
          :aria-expanded="open"
          :disabled="disabled"
          class="w-full justify-between"
        >
          <span class="truncate">{{ displayText }}</span>
          <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent class="mt-1 block w-full">
        <Command>
          <CommandInput
            class="h-9"
            v-model="searchProxy"
            :placeholder="searchPlaceholder"
          />
          <CommandList>
            <CommandEmpty v-if="!items.length">{{ emptyText }}</CommandEmpty>
            <CommandGroup>
              <CommandItem
                v-for="item in items"
                :key="String(item.value)"
                :value="String(item.value)"
                @select="(ev) => onSelect(String(ev.detail?.value ?? ''))"
              >
                {{ getItemDisplay(item) }}
                <CheckIcon
                  :class="cn(
                    'ml-auto',
                    String(item.value) === String(modelValue) ? 'opacity-100' : 'opacity-0',
                  )"
                />
              </CommandItem>
              <div
                v-if="hasMore"
                class="flex justify-center py-2"
              >
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="w-full text-muted-foreground"
                  :disabled="loadingMore"
                  @click="onLoadMore"
                >
                  {{ loadingMore ? 'Loading...' : 'Load more' }}
                </Button>
              </div>
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  </div>
</template>
