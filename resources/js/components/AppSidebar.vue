<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavAdminMain from '@/components/NavAdminMain.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, KeyRound, LayoutGrid, Lock, SquareTerminal, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems = {
    user: [
      {
          title: 'Dashboard',
          href: dashboard(),
          icon: LayoutGrid,
      },
    ],
    admin: [
      {
        title: "Admin",
        url: "#",
        icon: SquareTerminal,
        isActive: true,
        items: [
          {
              title: 'Users',
              href: '/users',
              icon: Users,
          },
          {
              title: 'Roles',
              href: '/roles',
              icon: Lock,
          },
          {
              title: 'Permissions',
              href: '/permissions',
              icon: KeyRound,
          },
        ],
      },
    ],
};

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
const isAdmin = (page.props as any).auth.is_admin as unknown;
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

        <SidebarContent :class="isAdmin ? 'flex-none' : ''">
            <NavMain :items="mainNavItems.user" />
        </SidebarContent>

        <SidebarContent v-if="isAdmin">
            <NavAdminMain :items="mainNavItems.admin" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
