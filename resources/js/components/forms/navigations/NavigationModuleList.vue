<script setup lang="ts">
/**
 * A searchable, paginated, full-CRUD list of navigation modules at one level of the
 * ref_id tree — either a navigation's top-level modules (refId null) or one module's
 * submodules (refId = that module's id). Shared by the details pane's Modules tab and
 * the submodules pane it opens, so the fetch/columns/CRUD plumbing lives in one place
 * instead of being duplicated per level.
 */
import { computed, ref, watch } from 'vue';
import { createColumnHelper } from '@tanstack/vue-table';
import Datatable from '@/components/Datatable.vue';
import { Button } from '@/components/ui/button';
import { createActionColumn } from '@/composables/datatable/datatableColumns';
import { useAjax } from '@/composables/useAjax';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { useNavigationModules, type NavigationModule } from '@/composables/navigationModules';
import { badge } from '@/lib/directoryBadges';
import { X } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
  navigationId: number;
  /** null = top-level modules (ref_id IS NULL); a module id = that module's submodules. */
  refId: number | null;
  /** Whether a row can be opened (e.g. into its own submodules pane). */
  clickable?: boolean;
  emptyMessage?: string;
  emptyDescription?: string;
}>(), {
  clickable: false,
  emptyMessage: 'No modules found',
  emptyDescription: 'No modules registered at this level yet.',
});

const emit = defineEmits<{
  'row-click': [module: NavigationModule];
  loaded: [total: number];
}>();

const { get } = useAjax();
const { canCreate, canEdit, canDelete } = useModulePermissions({ slug: 'navigation_modules' });

type ModulePage = { data: NavigationModule[]; current_page: number; per_page: number; total: number };

const modules = ref<NavigationModule[]>([]);
const modulesLoaded = ref(false);
const modulesLoading = ref(false);
const modulesError = ref('');
const modulesPagination = ref({ current_page: 1, per_page: 10, total: 0 });
const searchText = ref('');
const fetchToken = ref(0);

const fetchModules = async () => {
  const token = ++fetchToken.value;
  modulesLoading.value = true;
  modulesError.value = '';

  const params: Record<string, string | number> = {
    navigation_id: props.navigationId,
    ref_id: props.refId ?? 0,
    page: modulesPagination.value.current_page,
    per_page: modulesPagination.value.per_page,
  };
  const term = searchText.value.trim();
  if (term) params.search_string = term;

  try {
    const response = await get<{ navigation_modules: ModulePage }>('/navigation_modules', params);
    if (token !== fetchToken.value) return;
    if (!response.ok) throw new Error('Failed to fetch modules');

    const result = response.data?.navigation_modules;
    modules.value = result?.data ?? [];
    modulesPagination.value = {
      current_page: result?.current_page ?? 1,
      per_page: Number(result?.per_page ?? 10),
      total: result?.total ?? 0,
    };
    modulesLoaded.value = true;
    emit('loaded', modulesPagination.value.total);
  } catch {
    if (token !== fetchToken.value) return;
    modules.value = [];
    modulesError.value = 'Could not load these modules.';
  } finally {
    if (token === fetchToken.value) modulesLoading.value = false;
  }
};

const reloadModules = () => {
  modulesPagination.value.current_page = 1;
  void fetchModules();
};

void fetchModules();

// A pane can be reused (props patched rather than remounted) for a different navigation
// or parent module, so the list re-reads itself whenever which level it's showing changes.
watch(() => [props.navigationId, props.refId], () => {
  searchText.value = '';
  modulesLoaded.value = false;
  reloadModules();
});

const searchTimeout = ref<number | null>(null);
watch(searchText, () => {
  if (!modulesLoaded.value) return;
  if (searchTimeout.value) clearTimeout(searchTimeout.value);
  searchTimeout.value = window.setTimeout(() => reloadModules(), 500);
});

const clearSearch = () => { searchText.value = ''; };

const { createNavigationModule, editNavigationModule, deleteNavigationModule } = useNavigationModules({
  onMutated: reloadModules,
});

const handleRowClick = (item: NavigationModule) => {
  if (props.clickable) emit('row-click', item);
};

const columnHelper = createColumnHelper<NavigationModule>();
const baseColumns = [
  columnHelper.accessor('name', { header: 'Name' }),
  columnHelper.accessor('slug', { header: 'Slug' }),
  columnHelper.accessor('url', { header: 'URL', cell: ({ getValue }) => getValue() || '—' }),
  columnHelper.accessor('status', {
    header: 'Status',
    cell: ({ getValue }) => (Number(getValue()) === 1
      ? badge('Active', 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300')
      : badge('Inactive', 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400')),
  }),
];

const moduleActions = computed(() => {
  const actions: any[] = [];

  if (canEdit.value) {
    actions.push({
      slug: 'navigation_modules.edit',
      name: 'Edit',
      icon: 'SquarePen',
      color: 'blue',
      handler: (item: NavigationModule) => editNavigationModule(item),
    });
  }

  if (canDelete.value) {
    actions.push({
      slug: 'navigation_modules.delete',
      name: 'Delete',
      icon: 'Trash2',
      color: 'red',
      handler: (item: NavigationModule) => deleteNavigationModule(item),
      dynamicProps: (item: NavigationModule) => (item.deleted_at
        ? { name: 'Restore', icon: 'RotateCcw', color: 'green' }
        : {}),
    });
  }

  return actions;
});

const moduleColumns = computed(() => (moduleActions.value.length
  ? [...baseColumns, createActionColumn(moduleActions.value)]
  : baseColumns));
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-end gap-2">
      <Button v-if="canCreate" size="sm" @click="createNavigationModule(navigationId, refId ?? undefined)">
        Add Module
      </Button>
    </div>

    <div class="relative min-w-0 flex-1">
      <label class="sr-only" :for="`nm-list-search-${refId ?? 'top'}`">Search modules</label>
      <input
        :id="`nm-list-search-${refId ?? 'top'}`"
        v-model="searchText"
        type="text"
        placeholder="Search name or slug..."
        class="h-8 w-full rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface)] px-3 pr-8 text-xs text-[var(--color-text)] focus:border-transparent focus:ring-2 focus:ring-opacity-50"
        :style="{ '--tw-ring-color': 'var(--primary-color)' }" />
      <button
        v-if="searchText"
        class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none"
        aria-label="Clear module search"
        @click="clearSearch">
        <X class="h-3.5 w-3.5" />
      </button>
    </div>

    <Datatable
      :data="modules"
      :columns="moduleColumns"
      :pagination="modulesPagination"
      :loading="modulesLoading"
      :error="modulesError"
      :enable-search="false"
      :enable-row-click="clickable"
      :row-click="handleRowClick"
      :empty-message="emptyMessage"
      :empty-description="emptyDescription"
      :export-file-name="`navigation_${navigationId}_modules_${refId ?? 'top'}`"
      @update:pagination="(next: typeof modulesPagination) => { modulesPagination = next; fetchModules() }" />
  </div>
</template>
