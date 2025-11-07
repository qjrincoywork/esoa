<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import GlobalModal from '@/components/GlobalModal.vue';
import type { BreadcrumbItemType } from '@/types';
import { NotificationProvider } from '@/components/notification';

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
</template>
