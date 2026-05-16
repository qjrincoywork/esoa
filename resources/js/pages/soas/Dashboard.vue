<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Auth, User, UserDetail, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/components/ui/tooltip';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

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
const redirectToSoaList = (href: string) => {
  router.get(href);
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Dashboard" />

      <div class="grid auto-rows-min gap-4 md:grid-cols-3 p-4">
        <Card
          v-for="soaAging in soaAgings" :key="soaAging"
        >
          <CardContent>
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger class="w-full h-full">
                  <div class="cursor-pointer"
                    :class="soaAging.color"
                    @click="soaAging.count > 0 ? redirectToSoaList(soaAging.href) : null"
                  >
                    <div class="flex flex-col items-center justify-center">
                      <span
                        class="scroll-m-20 text-2xl font-semibold tracking-tight"
                      >
                        {{ soaAging.label }}
                        <Badge>{{ soaAging.count }}</Badge>
                      </span>
                    </div>
                  </div>
                </TooltipTrigger>
                <TooltipContent v-if="soaAging.count > 0">
                  <p>Click to view list</p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          </CardContent>
        </Card>
      </div>
  </AppLayout>
</template>
