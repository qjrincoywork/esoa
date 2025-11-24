<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import Icon from '@/components/Icon.vue';
import { computed } from 'vue';
import { urlIsActive } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';

interface NavigationModule {
    id: number;
    name: string;
    slug: string;
    url: string;
    icon: string;
    permission_id: number | null;
    permission_name: string | null;
}

interface Navigation {
    id: number;
    name: string;
    label: string;
    icon: string;
    modules: NavigationModule[];
}

const page = usePage();
const navigations = computed(() => (page.props as any).navigations as Navigation[] || []);

console.log(navigations)
// Generate href from url or slug
// const getModuleHref = (module: NavigationModule): string => {
//     // If url exists and is a valid path/route, use it
//     if (module.url) {
//         // If it starts with /, it's already a path
//         if (module.url.startsWith('/')) {
//             return module.url;
//         }
//         // If it contains a dot, it might be a route name (e.g., 'users.index')
//         // For now, convert to path format
//         if (module.url.includes('.')) {
//             // Convert 'users.index' to '/users' or keep as is
//             const routeParts = module.url.split('.');
//             return `/${routeParts[0]}`;
//         }
//         // Otherwise treat as path
//         return `/${module.url}`;
//     }
//     // Fallback to slug
//     return `/${module.slug}`;
// };

// Optional: Handle navigation programmatically using router
// const handleNavigation = (module: NavigationModule, event?: Event) => {
//     if (event) {
//         event.preventDefault();
//     }
//     const href = getModuleHref(module);
//     router.visit(href);
// };
</script>

<template>
  <component v-for="nav in navigations" v-if="navigations && navigations.length > 0" >
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ nav.label }}</SidebarGroupLabel>
        <SidebarMenu>
          <Collapsible
            :key="nav.name"
            as-child
            :default-open="true"
            class="group/collapsible"
          >
            <SidebarMenuItem>
              <CollapsibleTrigger as-child>
                <SidebarMenuButton :tooltip="nav.name">
                  <Icon 
                    v-if="nav.icon" 
                    :name="nav.icon" 
                  />
                  <span>{{ nav.name }}</span>
                  <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                </SidebarMenuButton>
              </CollapsibleTrigger>
              <CollapsibleContent>
                <SidebarMenuSub>
                  <SidebarMenuSubItem v-for="module in nav.modules" :key="module.name">
                    <SidebarMenuSubButton 
                        as-child
                        :is-active="urlIsActive(module.url, page.url)"
                        :tooltip="module.name">
                      <Link :href="module.url">
                        <Icon 
                          v-if="module.icon" 
                          :name="module.icon" 
                        />
                        <span>{{ module.name }}</span>
                      </Link>
                    </SidebarMenuSubButton>
                  </SidebarMenuSubItem>
                </SidebarMenuSub>
              </CollapsibleContent>
            </SidebarMenuItem>
          </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
  </component>
</template>
