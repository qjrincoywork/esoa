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
import { urlIsActive } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';
import { ChevronRight } from 'lucide-vue-next';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';

defineProps<{
    items: {
        title: string
        url: string
        icon?: LucideIcon
        isActive?: boolean
        items?: {
          title: string
          href: string
          icon: string
      }[]
    }[]
}>();
const page = usePage();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Admin</SidebarGroupLabel>
        <SidebarMenu>
          <Collapsible
            v-for="item in items"
            :key="item.title"
            as-child
            :default-open="item.isActive"
            class="group/collapsible"
          >
            <SidebarMenuItem>
              <CollapsibleTrigger as-child>
                <SidebarMenuButton :tooltip="item.title">
                  <component :is="item.icon" v-if="item.icon" />
                  <span>{{ item.title }}</span>
                  <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                </SidebarMenuButton>
              </CollapsibleTrigger>
              <CollapsibleContent>
                <SidebarMenuSub>
                  <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.title">
                    <SidebarMenuSubButton 
                        as-child
                        :is-active="urlIsActive(subItem.href, page.url)"
                        :tooltip="subItem.title">
                      <Link :href="subItem.href">
                        <component :is="subItem.icon" />
                        <span>{{ subItem.title }}</span>
                      </Link>
                    </SidebarMenuSubButton>
                  </SidebarMenuSubItem>
                </SidebarMenuSub>
              </CollapsibleContent>
            </SidebarMenuItem>
          </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
