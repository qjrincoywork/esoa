<script setup lang="ts">
import { onMounted } from 'vue';

type NavigationModule = {
    id?: number | string;
    name?: string;
    deleted_at?: string | null;
};

const props = defineProps<{
    navigationModule: NavigationModule;
    onReady: (api: { getFormData: () => FormData }) => void;
}>();

function getFormData(): FormData {
    const fd = new FormData();
    fd.set('id', String(props.navigationModule.id));
    return fd;
}

onMounted(() => props.onReady({ getFormData }));
</script>

<template>
    <p class="text-sm text-[var(--color-text-muted)]">
        <template v-if="navigationModule.deleted_at">
            Are you sure you want to restore
            <span class="font-medium text-[var(--color-text)]">{{ navigationModule.name }}</span>?
        </template>
        <template v-else>
            Are you sure you want to delete
            <span class="font-medium text-[var(--color-text)]">{{ navigationModule.name }}</span>?
            This action can be undone by restoring the record.
        </template>
    </p>
</template>
