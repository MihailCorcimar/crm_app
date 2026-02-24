<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type TenantItem = {
    id: number;
    name: string;
    slug: string;
    owner: string | null;
    role: string;
    can_create_tenants: boolean;
    settings: {
        brand_name?: string | null;
        brand_primary_color?: string | null;
        default_user_role?: string | null;
        allow_member_invites?: boolean;
    };
    onboarding: {
        completion_rate: number;
        is_complete: boolean;
    };
    can_manage_billing: boolean;
};

defineProps<{
    tenants: TenantItem[];
    canCreateTenant: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tenants', href: '/tenants' },
];

function roleLabel(role: string | null | undefined): string {
    if (role === 'owner') return 'proprietario';
    if (role === 'manager') return 'gestor';

    return 'membro';
}
</script>

<template>
    <Head title="Tenants" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Tenants</CardTitle>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="tenants.length > 0"
                            variant="outline"
                            class="border-slate-300 bg-slate-100 font-medium text-slate-900 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                            as-child
                        >
                            <Link href="/tenants/billing">Planos e faturação</Link>
                        </Button>
                        <Button
                            v-if="tenants.length > 0"
                            variant="outline"
                            class="border-slate-300 bg-slate-100 font-medium text-slate-900 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                            as-child
                        >
                            <Link href="/tenants/onboarding">Wizard inicial</Link>
                        </Button>
                        <Button v-if="canCreateTenant" as-child>
                            <Link href="/tenants/create">Criar tenant</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="!tenants.length" class="text-sm text-muted-foreground">
                        Sem tenants disponiveis para esta conta.
                    </div>

                    <div v-else class="grid gap-3 md:grid-cols-2">
                        <Link
                            v-for="tenant in tenants"
                            :key="tenant.id"
                            :href="`/tenants/${tenant.slug}`"
                            class="rounded-lg border p-4 transition-colors hover:bg-muted/40"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-medium">{{ tenant.name }}</h3>
                                <Badge variant="secondary">{{ roleLabel(tenant.role) }}</Badge>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">Slug: {{ tenant.slug }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">Proprietario: {{ tenant.owner ?? '-' }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Marca: {{ tenant.settings.brand_name ?? '-' }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Papel predefinido: {{ roleLabel(tenant.settings.default_user_role) }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Convites de membros: {{ tenant.settings.allow_member_invites ? 'ativos' : 'desativados' }}
                            </p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Progresso onboarding: {{ tenant.onboarding.completion_rate }}%
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Faturacao: {{ tenant.can_manage_billing ? 'com acesso' : 'sem acesso' }}
                            </p>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
