<script setup lang="ts">
import { computed, onMounted } from 'vue';
import type { User } from '@/composables/users';

const props = defineProps<{
    users: User[];
    newActiveValue: 0 | 1;
    onReady: (api: { getFormData: () => FormData }) => void;
}>();

const displayedUsers = computed(() => props.users.slice(0, 5));
const hiddenCount = computed(() => Math.max(0, props.users.length - 5));

const getFormData = (): FormData => {
    const formData = new FormData();
    props.users.forEach(u => formData.append('user_ids[]', String(u.id)));
    formData.append('is_active', String(props.newActiveValue));
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

        <div class="flex items-center gap-2">
            <span
                :class="[
                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                    newActiveValue
                        ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
                        : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                ]">
                {{ newActiveValue ? 'Inactive' : 'Active' }}
            </span>
            <svg class="w-4 h-4 shrink-0 text-[var(--color-text-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span
                :class="[
                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                    newActiveValue
                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                        : 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                ]">
                {{ newActiveValue ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <p class="text-xs text-[var(--color-text-muted)]">
            {{ newActiveValue
                ? `${users.length} user${users.length !== 1 ? 's' : ''} will be able to log in and access the system.`
                : `${users.length} user${users.length !== 1 ? 's' : ''} will be unable to log in until reactivated.` }}
        </p>
    </div>
</template>
