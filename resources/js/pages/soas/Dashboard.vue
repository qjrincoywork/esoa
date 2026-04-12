<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Auth, User, UserDetail, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const page = usePage();
const auth = computed(() => (page.props as any).auth as Auth);
const user = computed(() => auth.value?.user as User);
const userDetail = computed(() => user.value?.user_detail as UserDetail);
const { slug } = useModulePermissions();

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: slug.value,
  },
];
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
      <Head title="Dashboard" />

      <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
          <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
          >
            <PlaceholderPattern />
          </div>
          <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
          >
            <PlaceholderPattern />
          </div>
          <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
          >
            <PlaceholderPattern />
          </div>
        </div>
        <div
          class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
          <PlaceholderPattern />
        </div>
    </div>
  </AppLayout>
</template>
