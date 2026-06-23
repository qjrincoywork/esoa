<script setup lang="ts">
import { onMounted } from 'vue';
import type { User } from '@/composables/users';

const props = defineProps<{
    user: User;
    newActiveValue: 0 | 1;
    onReady: (api: { getFormData: () => FormData }) => void;
}>();

const getFormData = (): FormData => {
    const formData = new FormData();
    formData.append('id', String(props.user.id));
    formData.append('is_active', String(props.newActiveValue));
    return formData;
};

onMounted(() => props.onReady({ getFormData }));
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center gap-1.5">
            <span class="text-sm font-medium text-[var(--color-text)]">{{ user.username }}</span>
            <span v-if="user.email" class="text-xs text-[var(--color-text-muted)]">({{ user.email }})</span>
        </div>

        <div class="flex items-center gap-2">
            <span :class="[
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                newActiveValue
                    ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
                    : 'bg-green-100  text-green-800  dark:bg-green-900/30  dark:text-green-400',
            ]">
                {{ newActiveValue ? 'Inactive' : 'Active' }}
            </span>

            <svg class="w-4 h-4 shrink-0 text-[var(--color-text-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>

            <span :class="[
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                newActiveValue
                    ? 'bg-green-100  text-green-800  dark:bg-green-900/30  dark:text-green-400'
                    : 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            ]">
                {{ newActiveValue ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <p class="text-xs text-[var(--color-text-muted)]">
            {{ newActiveValue
                ? 'The user will be able to log in and access the system.'
                : 'The user will be unable to log in until reactivated.' }}
        </p>
    </div>
</template>
