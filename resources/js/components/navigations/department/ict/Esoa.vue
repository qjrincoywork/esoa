<script setup lang="ts">
import { type NavItem } from '@/types';
import { LayoutGrid } from 'lucide-vue-next';
import NavMain from '@/components/NavMain.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'User Dashboard',
        href: '/soa',
        icon: LayoutGrid,
    },
];

</script>

<template>
  <NavMain :items="mainNavItems" />
</template>
