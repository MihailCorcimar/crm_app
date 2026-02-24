<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, Calendar, Contact, FileText, Landmark, Settings, Shield, ShoppingCart, Truck, Users } from 'lucide-vue-next';
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
        title: 'Clientes',
        href: '/entities?type=customer',
        icon: Users,
    },
    {
        title: 'Fornecedores',
        href: '/entities?type=supplier',
        icon: Truck,
    },
    {
        title: 'Contactos',
        href: '/contacts',
        icon: Contact,
    },
    {
        title: 'Propostas',
        href: '/proposals',
        icon: FileText,
    },
    {
        title: 'Calendario',
        href: '/calendar',
        icon: Calendar,
    },
    {
        title: 'Encomendas - Clientes',
        href: '/orders',
        icon: ShoppingCart,
    },
    {
        title: 'Encomendas - Fornecedores',
        href: '/supplier-orders',
        icon: ShoppingCart,
    },
    {
        title: 'Ordens de Trabalho',
        href: '/work-orders',
        icon: FileText,
    },
    {
        title: 'Financeiro',
        href: '/finance/bank-accounts',
        icon: Landmark,
        children: [
            {
                title: 'Contas Bancarias',
                href: '/finance/bank-accounts',
            },
            {
                title: 'Conta Corrente Clientes',
                href: '/finance/customer-current-account',
            },
            {
                title: 'Faturas Fornecedores',
                href: '/supplier-invoices',
            },
        ],
    },
    {
        title: 'Arquivo Digital',
        href: '/digital-archive',
        icon: FileText,
    },
    {
        title: 'Gestao de Acessos',
        href: '/access/users',
        icon: Shield,
        children: [
            {
                title: 'Utilizadores',
                href: '/access/users',
            },
            {
                title: 'Permissoes',
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
                title: 'Gestao de tenants',
                href: '/tenants',
            },
            {
                title: 'Planos e faturação',
                href: '/tenants/billing',
            },
        ],
    },
    {
        title: 'Configuracoes',
        href: '/settings/entities/countries',
        icon: Settings,
        children: [
            {
                title: 'Entidades - Paises',
                href: '/settings/entities/countries',
            },
            {
                title: 'Contactos - Funcoes',
                href: '/settings/contacts/roles',
            },
            {
                title: 'Calendario - Tipos',
                href: '/settings/calendar/types',
            },
            {
                title: 'Calendario - Acoes',
                href: '/settings/calendar/actions',
            },
            {
                title: 'Artigos',
                href: '/settings/items',
            },
            {
                title: 'Financeiro - IVA',
                href: '/settings/finance/vat-rates',
            },
            {
                title: 'Logs',
                href: '/logs',
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
