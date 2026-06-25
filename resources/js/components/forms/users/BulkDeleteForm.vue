<script setup lang="ts">
import { computed, onMounted } from 'vue';
import type { User } from '@/composables/users';

const props = defineProps<{
    users: User[];
    action: 'delete' | 'restore';
    onReady: (api: { getFormData: () => FormData }) => void;
}>();

const displayedUsers = computed(() => props.users.slice(0, 5));
const hiddenCount = computed(() => Math.max(0, props.users.length - 5));
const isRestore = computed(() => props.action === 'restore');

const getFormData = (): FormData => {
    const formData = new FormData();
    props.users.forEach(u => formData.append('user_ids[]', String(u.id)));
    formData.append('action', props.action);
    return formData;
};

onMounted(() => props.onReady({ getFormData }));
</script>

<template>
    <div class="space-y-4">
        <div>
            <p class="text-sm text-[var(--color-text-muted)] mb-2">Selected users:</p>
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="user in displayedUsers"
                    :key="user.id"
                    class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium">
                    {{ user.username || user.email || user.id }}
                </span>
                <span
                    v-if="hiddenCount > 0"
                    class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                    +{{ hiddenCount }} more
                </span>
            </div>
        </div>

        <div
            :class="[
                'rounded-md px-3 py-2 text-xs',
                isRestore
                    ? 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300'
                    : 'bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300',
            ]">
            {{ isRestore
                ? `${users.length} user${users.length !== 1 ? 's' : ''} will be restored and accessible again.`
                : `${users.length} user${users.length !== 1 ? 's' : ''} will be soft-deleted. They can be restored later.` }}
        </div>

        <p class="text-xs text-[var(--color-text-muted)]">
            {{ isRestore
                ? `Are you sure you want to restore ${users.length > 1 ? 'these users' : 'this user'}?`
                : `Are you sure you want to delete ${users.length > 1 ? 'these users' : 'this user'}?` }}
        </p>
    </div>
</template>
