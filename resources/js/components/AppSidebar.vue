<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpenText,
    BriefcaseBusiness,
    Building2,
    Calendar,
    FileSignature,
    MessageSquare,
    Package,
    Settings,
    Shield,
    Users,
    Workflow,
} from 'lucide-vue-next';
import { computed } from 'vue';
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
import { type AppPageProps, type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage<AppPageProps>();

const modulePermissions = computed<Record<string, Record<string, boolean>>>(() =>
    page.props.auth?.module_permissions ?? {},
);

function canRead(module: string): boolean {
    return modulePermissions.value[module]?.read ?? true;
}

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (canRead('entities')) {
        items.push({
            title: 'Entidades',
            href: '/entities',
            icon: Building2,
        });
    }

    if (canRead('people')) {
        items.push({
            title: 'Pessoas',
            href: '/people',
            icon: Users,
        });
    }

    if (canRead('deals')) {
        items.push({
            title: 'Negócios',
            href: '/deals',
            icon: BriefcaseBusiness,
        });
    }

    if (canRead('calendar')) {
        items.push({
            title: 'Calendário',
            href: '/calendar',
            icon: Calendar,
        });
    }

    if (canRead('products')) {
        items.push({
            title: 'Produtos',
            href: '/items',
            icon: Package,
        });
    }

    if (canRead('deals')) {
        items.push({
            title: 'Relatórios',
            href: '/deals/product-stats',
            icon: BarChart3,
            children: [
                {
                    title: 'Estatísticas de Produtos',
                    href: '/deals/product-stats',
                },
            ],
        });
    }

    if (canRead('chat')) {
        items.push({
            title: 'Chat IA',
            href: '/ai/chat',
            icon: MessageSquare,
        });
    }

    if (canRead('forms')) {
        items.push({
            title: 'Formulários',
            href: '/lead-forms',
            icon: FileSignature,
        });
    }

    if (canRead('automations')) {
        items.push({
            title: 'Automações',
            href: '/automations/deal-rules',
            icon: Workflow,
            children: [
                {
                    title: 'Regras de inatividade',
                    href: '/automations/deal-rules',
                },
            ],
        });
    }

    if (canRead('access')) {
        items.push({
            title: 'Administração',
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
        });
    }

    if (canRead('tenants')) {
        items.push({
            title: 'Tenants',
            href: '/tenants',
            icon: Building2,
            children: [
                {
                    title: 'Gestão de Tenants',
                    href: '/tenants',
                },
                {
                    title: 'Planos e Faturação',
                    href: '/tenants/billing',
                },
            ],
        });
    }

    if (canRead('settings')) {
        items.push({
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
        });
    }

    if (canRead('logs')) {
        items.push({
            title: 'Logs',
            href: '/logs',
            icon: BookOpenText,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="floating">
        <SidebarHeader class="border-b border-sidebar-border/70 bg-[linear-gradient(135deg,hsl(154_100%_97%)_0%,hsl(0_0%_100%)_45%,hsl(206_100%_97%)_100%)]">
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

        <SidebarFooter class="border-t border-sidebar-border/70">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

