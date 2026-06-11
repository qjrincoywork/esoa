<script setup lang="ts">
import { onMounted } from 'vue';
import type { User } from '@/composables/users';

const props = defineProps<{
    users: User[];
    onReady: (api: { getFormData: () => FormData }) => void;
}>();

const getFormData = (): FormData => {
    const formData = new FormData();
    props.users.forEach(u => formData.append('ids[]', String(u.id)));
    return formData;
};

onMounted(() => props.onReady({ getFormData }));
</script>

<template>
    <div class="space-y-3">
        <p class="text-sm text-[var(--color-text-muted)]">
            <template v-if="users.length === 1">
                Verify <span class="font-medium text-[var(--color-text)]">{{ users[0]?.username }}</span>?
                This will set their email as verified and mark the account as approved.
            </template>
            <template v-else>
                Verify <span class="font-medium text-[var(--color-text)]">{{ users.length }}</span> selected users?
                This will set their emails as verified and mark their accounts as approved.
            </template>
        </p>

        <ul
            v-if="users.length > 1"
            class="max-h-40 overflow-y-auto divide-y divide-[var(--color-border)] rounded border border-[var(--color-border)] text-xs"
        >
            <li
                v-for="u in users"
                :key="u.id"
                class="px-3 py-1.5 flex justify-between gap-4 text-[var(--color-text)]"
            >
                <span class="font-medium">{{ u.username }}</span>
                <span class="text-[var(--color-text-muted)] truncate">{{ u.email }}</span>
            </li>
        </ul>
    </div>
</template>
