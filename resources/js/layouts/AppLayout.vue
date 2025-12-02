<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import GlobalModal from '@/components/GlobalModal.vue';
import type { BreadcrumbItemType } from '@/types';
import { NotificationProvider } from '@/components/notification';
import { isLoading } from '@/composables/useLoader';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
    <GlobalModal />
    <NotificationProvider></NotificationProvider>
    <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <Loader />
    </div>
</template>
