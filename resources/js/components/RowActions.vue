<script setup lang="ts">
/**
 * A row's actions in one narrow cell: the inline ones as icon buttons, the rest named in
 * a "more" menu, with destructive ones set apart at its foot.
 *
 * Rendered by `createRowActionsColumn`, which resolves the actions per row.
 */
import { computed, h, type FunctionalComponent } from 'vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { RowAction } from '@/composables/datatable/rowActions';
import { MoreHorizontal } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    actions: RowAction[];
    /** Names the menu button for screen readers, e.g. "Actions for jdelacruz". */
    menuLabel?: string;
  }>(),
  { menuLabel: 'More actions' },
);

const inline = computed(() => props.actions.filter((action) => action.inline));
const menu = computed(() => props.actions.filter((action) => !action.inline && !action.destructive));
const destructive = computed(() => props.actions.filter((action) => !action.inline && action.destructive));

/** A lucide icon given as a component, or by the name a navigation module stores. */
const ActionIcon: FunctionalComponent<{ icon?: RowAction['icon'] }> = ({ icon }) => {
  if (typeof icon === 'string') return h(Icon, { name: icon });

  return icon ? h(icon) : null;
};
</script>

<template>
  <div class="flex items-center gap-0.5">
    <Tooltip v-for="action in inline" :key="action.key">
      <TooltipTrigger as-child>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          class="cursor-pointer text-muted-foreground hover:text-foreground"
          :aria-label="action.label"
          @click="action.onSelect()">
          <ActionIcon :icon="action.icon" />
        </Button>
      </TooltipTrigger>
      <TooltipContent>{{ action.label }}</TooltipContent>
    </Tooltip>

    <!--
      Not modal: most actions open a modal or pane of their own, and a modal menu holds
      the page inert until it has finished closing.
    -->
    <DropdownMenu v-if="menu.length || destructive.length" :modal="false">
      <DropdownMenuTrigger as-child>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          class="cursor-pointer text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
          :aria-label="menuLabel">
          <MoreHorizontal />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-56">
        <DropdownMenuItem
          v-for="action in menu"
          :key="action.key"
          class="cursor-pointer"
          @select="action.onSelect()">
          <ActionIcon :icon="action.icon" />
          {{ action.label }}
        </DropdownMenuItem>
        <DropdownMenuSeparator v-if="menu.length && destructive.length" />
        <!-- Coloured here: the item's destructive variant uses destructive-foreground, a near-white in this theme -->
        <DropdownMenuItem
          v-for="action in destructive"
          :key="action.key"
          class="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-700 dark:text-red-400 dark:focus:bg-red-950/40 dark:focus:text-red-300"
          @select="action.onSelect()">
          <ActionIcon :icon="action.icon" class="text-current" />
          {{ action.label }}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>
