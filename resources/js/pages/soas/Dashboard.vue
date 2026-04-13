<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Auth, User, UserDetail, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

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
const soaAgings = computed(() => (page.props as any).soa_agings?.data as SoaAgingCountResource[]);
const redirectToSoaList = (dueIn: number) => {
  router.get(`${slug.value}/list`, { due_in: dueIn });
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
      <Head title="Dashboard" />

      <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3" v-for="soaAging in soaAgings" :key="soaAging">
          <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            v-if="soaAging.count > 0"
          >
            <div class="flex flex-col items-center justify-center">
              <div class="p-4 cursor-pointer"
                @click="redirectToSoaList(soaAging.value)"
              >
                <h3
                  class="scroll-m-20 text-2xl font-semibold tracking-tight"
                  :class="soaAging.color"
                >
                  <span>
                    {{ soaAging.label }}
                    <Badge>{{ soaAging.count }}</Badge>
                  </span>
                </h3>
              </div>
            </div>
          </div>
        </div>
    </div>
  </AppLayout>
</template>
