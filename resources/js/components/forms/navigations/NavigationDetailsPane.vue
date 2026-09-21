<script setup lang="ts">
/**
 * One navigation, opened from the listing.
 *
 * "Details" answers what the navigation is; "Modules" answers what it contains — its
 * top-level menu entries (ref_id IS NULL). Clicking a module row opens a second, top-side
 * pane listing that module's own submodules (ref_id = the module's id), with the same
 * create/edit/delete a reader would get on the standalone Navigation Modules page.
 */
import { computed, ref, watch } from 'vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import RightPane from '@/components/RightPane.vue';
import { usePane } from '@/composables/usePane';
import NavigationModuleList from '@/components/forms/navigations/NavigationModuleList.vue';
import NavigationSubmodulePane from '@/components/forms/navigations/NavigationSubmodulePane.vue';
import type { NavigationModule } from '@/composables/navigationModules';
import type { NavigationRow } from '@/composables/navigationDetails';

const props = defineProps<{
  navigation: NavigationRow;
}>();

const activeTab = ref('details');

// ── Details ──────────────────────────────────────────────────────────────
type Fact = { label: string; value: string | null | undefined; mono?: boolean };

const isActive = computed(() => Number(props.navigation.status) === 1);

const facts = computed<Fact[]>(() => [
  { label: 'Name', value: props.navigation.name },
  { label: 'Label', value: props.navigation.label },
  { label: 'Icon', value: props.navigation.icon, mono: true },
  { label: 'Order Number', value: props.navigation.order_number?.toString() },
  { label: 'Created By', value: props.navigation.created_by?.toString() },
  { label: 'Created At', value: props.navigation.created_at },
  { label: 'Updated At', value: props.navigation.updated_at },
]);

// ── Modules (top-level only — ref_id IS NULL) ───────────────────────────────
const moduleCount = ref<number | null>(null);
const modulesTabLabel = computed(() => (
  moduleCount.value !== null ? `Modules (${moduleCount.value.toLocaleString()})` : 'Modules'
));

// Mounted (and thus fetched) only once the Modules tab is first opened; stays mounted
// afterwards so switching tabs back and forth doesn't refetch.
const modulesTabOpened = ref(false);
watch(activeTab, (tab) => {
  if (tab === 'modules') modulesTabOpened.value = true;
});

// ── Submodules pane (top side) ──────────────────────────────────────────────
const { openPane: openTopPane, closePane, topPane } = usePane();

const openSubmodulesPane = (module: NavigationModule) => {
  if (!module?.id) return;

  openTopPane({
    side: 'top',
    title: `${module.name ?? 'Module'} — Submodules`,
    component: NavigationSubmodulePane,
    componentProps: {
      navigationId: props.navigation.id,
      parentModule: { id: Number(module.id), name: module.name ?? `#${module.id}` },
    },
  });
};
</script>

<template>
  <div class="flex w-full flex-col gap-3">
    <div class="flex flex-wrap items-center gap-2">
      <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="isActive
          ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
          : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'">
        {{ isActive ? 'Active' : 'Inactive' }}
      </span>
      <span
        v-if="navigation.deleted_at"
        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
        Deleted
      </span>
    </div>

    <Tabs v-model="activeTab" default-value="details">
      <TabsList>
        <TabsTrigger class="cursor-pointer" value="details">
          Details
        </TabsTrigger>
        <TabsTrigger class="cursor-pointer" value="modules">
          {{ modulesTabLabel }}
        </TabsTrigger>
      </TabsList>

      <!-- Details -->
      <TabsContent value="details" class="mt-3">
        <dl class="divide-y divide-[var(--color-border)] text-sm">
          <div v-for="fact in facts" :key="fact.label" class="grid grid-cols-3 gap-3 py-2">
            <dt class="text-[var(--color-text-muted)]">{{ fact.label }}</dt>
            <dd class="col-span-2 break-words" :class="fact.mono ? 'font-mono text-xs' : ''">
              {{ fact.value || '—' }}
            </dd>
          </div>
        </dl>
      </TabsContent>

      <!-- Modules -->
      <TabsContent value="modules" class="mt-3">
        <p class="mb-3 text-xs text-[var(--color-text-muted)]">
          Top-level modules registered under this navigation. Click a row to see its submodules.
        </p>
        <NavigationModuleList
          v-if="modulesTabOpened"
          :navigation-id="navigation.id"
          :ref-id="null"
          clickable
          empty-description="This navigation has no top-level modules yet."
          @row-click="openSubmodulesPane"
          @loaded="moduleCount = $event" />
      </TabsContent>
    </Tabs>

    <RightPane
      :open="topPane.open"
      side="top"
      :title="topPane.title"
      :loading="topPane.loading"
      :error="topPane.error"
      :content-component="topPane.contentComponent"
      :component-props="topPane.componentProps"
      @update:open="(v) => { if (!v && !topPane.loading) closePane('top') }" />
  </div>
</template>
