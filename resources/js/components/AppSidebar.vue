<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpenText, BriefcaseBusiness, Building2, Calendar, Settings, Shield, Users } from 'lucide-vue-next';
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
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Entidades',
        href: '/entities',
        icon: Building2,
    },
    {
        title: 'Pessoas',
        href: '/people',
        icon: Users,
    },
    {
        title: 'Negócios',
        href: '/deals',
        icon: BriefcaseBusiness,
    },
    {
        title: 'Calendário',
        href: '/calendar',
        icon: Calendar,
    },
    {
        title: 'Logs',
        href: '/logs',
        icon: BookOpenText,
    },
    {
        title: 'Gestão de Acessos',
        href: '/access/users',
        icon: Shield,
        children: [
            {
                title: 'Utilizadores',
                href: '/access/users',
            },
            {
                title: 'Permissões',
                href: '/access/permission-groups',
            },
        ],
    },
    {
        title: 'Tenants',
        href: '/tenants',
        icon: Building2,
        children: [
            {
                title: 'Gestão de tenants',
                href: '/tenants',
            },
            {
                title: 'Planos e faturação',
                href: '/tenants/billing',
            },
        ],
    },
    {
        title: 'Configurações',
        href: '/settings/entities/countries',
        icon: Settings,
        children: [
            {
                title: 'Entidades - Países',
                href: '/settings/entities/countries',
            },
            {
                title: 'Pessoas - Funções',
                href: '/settings/contacts/roles',
            },
            {
                title: 'Calendário - Tipos',
                href: '/settings/calendar/types',
            },
            {
                title: 'Calendário - Ações',
                href: '/settings/calendar/actions',
            },
            {
                title: 'Negócios - Etapas',
                href: '/settings/deals/stages',
            },
            {
                title: 'Empresa',
                href: '/settings/company',
            },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
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

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>