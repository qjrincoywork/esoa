<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import CustomNav from '@/components/CustomNav.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, Navigation } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { BookOpen, Folder, KeyRound, LayoutGrid, Lock, SquareTerminal, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import Esoa from './navigations/department/ict/Esoa.vue';

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];
const page = usePage();
const isSuperadmin = (page.props as any).auth.is_superadmin as unknown;
const navigations = computed(() => (page.props as any).navigations as Navigation[] || []);

</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent v-for="nav in navigations" :class="isSuperadmin ? 'flex-none' : ''">
          <CustomNav :navigation="nav"></CustomNav>
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
