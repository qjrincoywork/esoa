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
import type { Navigation } from '@/types';
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

const props = defineProps<{
  navigation: {
    icon: string;
    id: number;
    label: string;
    modules: [];
    name: string;
  }
}>();

const page = usePage();
// const navigation = props.navigation as Navigation[] || [];
const navigation = computed(() => props.navigation as Navigation || []);
</script>

<template>
  <component v-if="navigation" >
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ navigation.label }}</SidebarGroupLabel>
        <SidebarMenu>
          <Collapsible
            :key="navigation.name"
            as-child
            :default-open="true"
            class="group/collapsible"
          >
            <SidebarMenuItem>
              <CollapsibleTrigger as-child>
                <SidebarMenuButton :tooltip="navigation.name">
                  <Icon 
                    v-if="navigation.icon" 
                    :name="navigation.icon" 
                  />
                  <span>{{ navigation.name }}</span>
                  <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                </SidebarMenuButton>
              </CollapsibleTrigger>
              <CollapsibleContent>
                <SidebarMenuSub>
                  <SidebarMenuSubItem v-for="module in navigation.modules" :key="module.name">
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
