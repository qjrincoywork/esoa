<script setup lang="ts">
import { onMounted } from 'vue';
import type { User } from '@/composables/users';

const props = defineProps<{
    user: User;
    isDeleted: boolean;
    onReady: (api: { getFormData: () => FormData }) => void;
}>();

const getFormData = (): FormData => {
    const formData = new FormData();
    formData.append('id', String(props.user.id));
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

        <div :class="[
            'rounded-md px-3 py-2 text-xs',
            isDeleted
                ? 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300'
                : 'bg-red-50   dark:bg-red-900/20   text-red-800   dark:text-red-300',
        ]">
            {{ isDeleted
                ? 'This user was previously deleted. Restoring will reinstate their record and allow them to log in.'
                : 'This will soft-delete the user. They will be hidden from the system and unable to log in.' }}
        </div>

        <p class="text-xs text-[var(--color-text-muted)]">
            {{ isDeleted ? 'Are you sure you want to restore this user?' : 'Are you sure you want to delete this user?' }}
        </p>
    </div>
</template>
