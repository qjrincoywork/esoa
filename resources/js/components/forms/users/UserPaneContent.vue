<script setup lang="ts">
/**
 * Right-pane body for a user: their details, and their account/branch mapping.
 *
 * The pane is reached two ways and opens on a different tab for each — a row click
 * lands on the details tab, the mapping action lands on the mapping tab — but it is
 * one pane either way, so an administrator can read who a user is and change what they
 * can see without leaving the screen. The mapping tab appears only for a role that
 * holds the mapping permission, and only mounts its panels once it is on screen, so a
 * pane opened to read details never queries the account directory.
 */
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import UserAccountMappingForm from '@/components/forms/users/UserAccountMappingForm.vue';
import { useModulePermissions } from '@/composables/useModulePermissions';
import type { User, UserAccountMapping, UserPaneDetails, UserPaneTab } from '@/composables/users';

const props = withDefaults(
  defineProps<{
    /** The list row the pane was opened from — the fallback when the fetch failed. */
    user: User;
    details?: UserPaneDetails | null;
    mappings?: UserAccountMapping[];
    accountTypes?: Array<{ value: string | number; name: string }>;
    initialTab?: UserPaneTab;
  }>(),
  {
    details: null,
    mappings: () => [],
    accountTypes: () => [],
    initialTab: 'details',
  },
);

const { slug, hasPermission } = useModulePermissions();

/** Prefer the fetched user; fall back to the row so the pane is never empty. */
const user = computed<UserPaneDetails>(() => props.details ?? (props.user as UserPaneDetails));

/**
 * The mapping tab needs both the permission to manage mappings and a user the mappings
 * would actually apply to — only the account-scoped types are mapped to accounts and
 * branches, and the server decides which those are
 * ({@see \App\Enums\UserType::allowsAccountMapping()}), so the tab is simply absent for
 * everyone else rather than offering a change that would have no effect.
 */
const canMap = computed(() =>
  hasPermission(`${slug.value}.account_mapping`) && user.value?.allows_account_mapping === true,
);

/** The requested tab, unless it is not on offer for this user. */
const resolveInitialTab = (): UserPaneTab =>
  props.initialTab === 'account_mapping' && canMap.value ? 'account_mapping' : 'details';

const activeTab = ref<UserPaneTab>(resolveInitialTab());

const mappings = ref<UserAccountMapping[]>([...props.mappings]);

// Reopening the pane swaps these props on the same component instance rather than
// mounting a fresh one, so the local copies have to follow them; snapshotting once
// would leave the second user looking at the first user's mappings and tab.
watch(
  () => props.mappings,
  (next) => { mappings.value = [...next]; },
  { deep: true },
);

watch(
  [() => props.initialTab, () => user.value?.id],
  () => { activeTab.value = resolveInitialTab(); },
);

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

  // Roles arrive as names from the pane payload, or as objects on a raw list row.
  return list.map((role: any) => (typeof role === 'string' ? role : role?.name)).filter(Boolean);
});

const mappingSummary = computed(() => {
  const total = mappings.value.length;

  if (!user.value?.allows_account_mapping) return 'Not applicable';
  if (total === 0) return 'None mapped';

  return `${total} ${total === 1 ? 'mapping' : 'mappings'}`;
});

const onMappingSaved = (saved: UserAccountMapping[]) => {
  mappings.value = saved;
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
      </TabsList>

      <TabsContent value="details">
        <div class="flex flex-col gap-4 pt-2">
          <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="isActive ? 'default' : 'secondary'">
              {{ isActive ? 'Active' : 'Inactive' }}
            </Badge>
            <Badge v-if="user?.is_verified" variant="outline">Verified</Badge>
            <Badge v-if="user?.deleted_at" variant="destructive">Deleted</Badge>
            <Badge variant="outline">{{ mappingSummary }}</Badge>
          </div>

          <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
            <div
              v-for="row in detailRows"
              :key="row.label"
              class="flex flex-col border-b border-[var(--color-border)] py-1.5 last:border-0">
              <dt class="text-xs text-[var(--color-text-muted)]">{{ row.label }}</dt>
              <dd class="truncate text-sm" :title="String(row.value)">{{ row.value }}</dd>
            </div>
          </dl>

          <div v-if="roles.length">
            <p class="mb-1 text-xs text-[var(--color-text-muted)]">Roles</p>
            <div class="flex flex-wrap gap-1.5">
              <Badge v-for="role in roles" :key="role" variant="secondary">{{ role }}</Badge>
            </div>
          </div>
        </div>
      </TabsContent>

      <TabsContent v-if="canMap" value="account_mapping">
        <!-- Mounted only while this tab is open, so the directory is queried on demand -->
        <div v-if="activeTab === 'account_mapping'" class="pt-2">
          <UserAccountMappingForm
            :user-id="user?.id ?? props.user?.id ?? ''"
            :mappings="mappings"
            :account-types="props.accountTypes"
            :allows-mapping="user?.allows_account_mapping !== false"
            :limit="user?.account_mapping_limit ?? null"
            :type-label="user?.type_label ?? null"
            @saved="onMappingSaved" />
        </div>
      </TabsContent>
    </Tabs>
  </div>
</template>
