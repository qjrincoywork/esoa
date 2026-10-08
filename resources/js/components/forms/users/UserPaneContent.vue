<script setup lang="ts">
/**
 * Right-pane body for a user: their details, their account/branch mapping, and what
 * they have done.
 *
 * The pane is reached two ways and opens on a different tab for each — a row click
 * lands on the details tab, the mapping action lands on the mapping tab — but it is
 * one pane either way, so an administrator can read who a user is, change what they
 * can see and look back over what they did without leaving the screen.
 *
 * It opens at once on the list row it came from, and each tab fetches its own data the
 * first time it is shown: details from `users.details` (no HMS), mappings from
 * `users.account_mapping` (the one request that labels codes through HMS), the activity
 * trail a page at a time from its own list. What a tab loaded is kept while the pane
 * shows the same user, so switching back costs nothing; opening another user starts
 * over. The mapping and activity tabs appear only for a role holding their permission.
 */
import { computed, ref, watch } from 'vue';
import LazyContentState from '@/components/LazyContentState.vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import UserAccountMappingForm from '@/components/forms/users/UserAccountMappingForm.vue';
import UserActivityLogList from '@/components/forms/users/UserActivityLogList.vue';
import { useLazyResource } from '@/composables/useLazyResource';
import { useModulePermissions } from '@/composables/useModulePermissions';
import {
  useUsers,
  type User,
  type UserAccountMapping,
  type UserMappingRules,
  type UserPaneDetails,
  type UserPaneTab,
} from '@/composables/users';

const props = withDefaults(
  defineProps<{
    /** The list row the pane was opened from — shown until, or instead of, the fetched details. */
    user: User;
    initialTab?: UserPaneTab;
  }>(),
  { initialTab: 'details' },
);

const { slug, hasPermission } = useModulePermissions();
const { getUserDetails, getUserAccountMapping } = useUsers();

const userId = computed(() => props.user?.id ?? '');

// ─── Per-tab data, each fetched the first time its tab is shown ───────────
const {
  data: details,
  loading: detailsLoading,
  failed: detailsFailed,
  load: loadDetails,
  reset: resetDetails,
} = useLazyResource(() => getUserDetails(userId.value));

const {
  data: mapping,
  loading: mappingLoading,
  failed: mappingFailed,
  load: loadMapping,
  reset: resetMapping,
  set: setMapping,
} = useLazyResource(() => getUserAccountMapping(userId.value));

/** Prefer the fetched details; the row stands in until they land, or if they cannot. */
const user = computed<UserPaneDetails>(() => details.value ?? (props.user as UserPaneDetails));

/**
 * The type's mapping rules: the mapping tab's own copy once it has loaded, otherwise
 * what the details — or the row — already say, which is enough to decide whether the
 * tab is on offer before anything about it is fetched.
 */
const mappingRules = computed<UserMappingRules>(() => mapping.value?.rules ?? {
  type: user.value?.type ?? null,
  type_label: user.value?.type_label ?? null,
  allows_account_mapping: user.value?.allows_account_mapping === true,
  account_mapping_limit: user.value?.account_mapping_limit ?? null,
});

/**
 * The mapping tab needs both the permission to manage mappings and a user the mappings
 * would actually apply to — only the account-scoped types are mapped to accounts and
 * branches, and the server decides which those are
 * ({@see \App\Enums\UserType::allowsAccountMapping()}), so the tab is simply absent for
 * everyone else rather than offering a change that would have no effect.
 */
const canMap = computed(() =>
  hasPermission(`${slug.value}.account_mapping`) && mappingRules.value.allows_account_mapping,
);

/** The user's audit trail is the trail's own audience (superadmin), granted as a permission. */
const canViewActivity = computed(() => hasPermission(`${slug.value}.activity_logs`));

/** Which tabs are on offer for this user; details always are. */
const availableTabs = computed<Record<UserPaneTab, boolean>>(() => ({
  details: true,
  account_mapping: canMap.value,
  activity_logs: canViewActivity.value,
}));

/** The requested tab, unless it is not on offer for this user. */
const resolveInitialTab = (): UserPaneTab =>
  availableTabs.value[props.initialTab] ? props.initialTab : 'details';

const activeTab = ref<UserPaneTab>(resolveInitialTab());

/**
 * What showing each tab fetches. The activity tab has none here: its list fetches its
 * own pages when it mounts, which only happens while the tab is shown.
 */
const TAB_LOADERS: Partial<Record<UserPaneTab, () => Promise<void>>> = {
  details: () => loadDetails(),
  account_mapping: () => loadMapping(),
};

const loadTab = (tab: UserPaneTab) => void TAB_LOADERS[tab]?.();

watch(activeTab, loadTab, { immediate: true });

// Reopening the pane swaps the row on the same component instance rather than mounting
// a fresh one, so another user means forgetting the last one's data and starting over.
watch(userId, () => {
  resetDetails();
  resetMapping();
  activeTab.value = resolveInitialTab();
  loadTab(activeTab.value);
});

watch(() => props.initialTab, () => {
  activeTab.value = resolveInitialTab();
});

// ─── Details tab ──────────────────────────────────────────────────────────
const isActive = computed(() => Number(user.value?.is_active) !== 0);

/**
 * The details worth a row each, kept as data so the tab stays one loop and a new field
 * means one entry rather than more markup. Blank values are dropped.
 */
const detailRows = computed(() =>
  [
    { label: 'Username', value: user.value?.username },
    { label: 'Email', value: user.value?.email },
    { label: 'Full Name', value: user.value?.full_name },
    { label: 'User Type', value: user.value?.type_label },
    { label: 'Department', value: user.value?.department },
    { label: 'Position', value: user.value?.position },
    { label: 'Employee No.', value: user.value?.employee_no },
    { label: 'Agent Code', value: user.value?.agent_code },
    { label: 'Birthdate', value: user.value?.birthdate },
    { label: 'Gender', value: user.value?.gender },
    { label: 'Civil Status', value: user.value?.civil_status },
    { label: 'Citizenship', value: user.value?.citizenship },
    { label: 'Verified', value: user.value?.email_verified_at },
    { label: 'Created', value: user.value?.created_at },
  ].filter((row) => row.value !== null && row.value !== undefined && row.value !== ''),
);

const roles = computed<string[]>(() => {
  const list = user.value?.roles ?? [];

  // Roles arrive as names from the details payload, or as objects on a raw list row.
  return list.map((role: any) => (typeof role === 'string' ? role : role?.name)).filter(Boolean);
});

/**
 * How many mappings the user holds: the mapping tab's list once it is loaded (it is the
 * fresher of the two after a save), otherwise the count the details carried. Unknown —
 * and so not shown — until one of them has arrived.
 */
const mappingCount = computed<number | null>(() =>
  mapping.value?.user_accounts.length ?? user.value?.account_mapping_count ?? null,
);

const mappingSummary = computed(() => {
  if (!mappingRules.value.allows_account_mapping) return 'Not applicable';
  if (mappingCount.value === null) return null;
  if (mappingCount.value === 0) return 'None mapped';

  return `${mappingCount.value} ${mappingCount.value === 1 ? 'mapping' : 'mappings'}`;
});

// ─── Mapping tab ──────────────────────────────────────────────────────────
/** A save returns the stored set; keep it, so the tab and the summary need no refetch. */
const onMappingSaved = (saved: UserAccountMapping[]) => {
  if (mapping.value) setMapping({ ...mapping.value, user_accounts: saved });
};
</script>

<template>
  <div class="flex w-full flex-col">
    <Tabs v-model="activeTab" :default-value="activeTab">
      <TabsList>
        <TabsTrigger class="cursor-pointer" value="details">User Details</TabsTrigger>
        <TabsTrigger v-if="canMap" class="cursor-pointer" value="account_mapping">
          Accounts &amp; Branches
        </TabsTrigger>
        <TabsTrigger v-if="canViewActivity" class="cursor-pointer" value="activity_logs">
          Activity
        </TabsTrigger>
      </TabsList>

      <TabsContent value="details">
        <div class="flex flex-col gap-4 pt-2">
          <!-- The row's fields show at once; the full details fill in when they land -->
          <LazyContentState
            inline
            :loading="detailsLoading"
            :failed="detailsFailed"
            loading-text="Loading full details…"
            failed-text="Full details could not be loaded — showing what the list knows."
            @retry="loadDetails(true)" />

          <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="isActive ? 'default' : 'secondary'">
              {{ isActive ? 'Active' : 'Inactive' }}
            </Badge>
            <Badge v-if="user?.is_verified" variant="outline">Verified</Badge>
            <Badge v-if="user?.deleted_at" variant="destructive">Deleted</Badge>
            <Badge v-if="mappingSummary" variant="outline">{{ mappingSummary }}</Badge>
          </div>

          <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
            <div
              v-for="row in detailRows"
              :key="row.label"
              class="flex flex-col border-b py-1.5 last:border-0">
              <dt class="text-xs text-muted-foreground">{{ row.label }}</dt>
              <dd class="truncate text-sm" :title="String(row.value)">{{ row.value }}</dd>
            </div>
          </dl>

          <div v-if="roles.length">
            <p class="mb-1 text-xs text-muted-foreground">Roles</p>
            <div class="flex flex-wrap gap-1.5">
              <Badge v-for="role in roles" :key="role" variant="secondary">{{ role }}</Badge>
            </div>
          </div>
        </div>
      </TabsContent>

      <TabsContent v-if="canMap" value="account_mapping">
        <!-- Mounted only while this tab is open, so the directory is queried on demand -->
        <div v-if="activeTab === 'account_mapping'" class="pt-2">
          <LazyContentState
            :loading="mappingLoading"
            :failed="mappingFailed"
            loading-text="Loading accounts & branches…"
            failed-text="The account & branch mapping could not be loaded."
            @retry="loadMapping(true)" />

          <UserAccountMappingForm
            v-if="mapping"
            :user-id="userId"
            :mappings="mapping.user_accounts"
            :account-types="mapping.account_types"
            :allows-mapping="mapping.rules.allows_account_mapping"
            :limit="mapping.rules.account_mapping_limit"
            :type-label="mapping.rules.type_label"
            @saved="onMappingSaved" />
        </div>
      </TabsContent>

      <TabsContent v-if="canViewActivity" value="activity_logs">
        <!-- Mounted only while this tab is open, so the trail is queried on demand -->
        <div v-if="activeTab === 'activity_logs'" class="pt-2">
          <UserActivityLogList :user-id="userId" />
        </div>
      </TabsContent>
    </Tabs>
  </div>
</template>
