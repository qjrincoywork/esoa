<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavAdminMain from '@/components/NavAdminMain.vue';
import NavMain from '@/components/NavMain.vue';
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
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { BookOpen, Folder, KeyRound, LayoutGrid, Lock, SquareTerminal, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import Esoa from './navigations/department/ict/Esoa.vue';

const mainNavItems = {
    superadmin: [
      {
        title: "ICT Admin",
        url: "#",
        icon: SquareTerminal,
        isActive: true,
        items: [
          {
              title: 'Admin Dashboard',
              href: dashboard(),
              icon: LayoutGrid,
          },
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
const department = ref((page.props as any).auth.user_detail?.department_id)
const isSuperadmin = (page.props as any).auth.is_superadmin as unknown;
const departmentComponent = computed(() => {
  switch (department.value) {
    case 8:
      return Esoa
    default:
      return null
  }
})
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

        <SidebarContent :class="isSuperadmin ? 'flex-none' : ''">
          <CustomNav></CustomNav>
          <!-- <component :is="departmentComponent" /> -->
        </SidebarContent>

        <!-- <SidebarContent v-if="isSuperadmin">
            <NavAdminMain :items="mainNavItems.superadmin" />
        </SidebarContent> -->

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
