<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type TenantMember = {
    id: number;
    name: string;
    email: string;
    role: string;
    can_create_tenants: boolean;
};

type TenantDetails = {
    id: number;
    name: string;
    slug: string;
    owner: {
        id: number;
        name: string;
        email: string;
    };
    settings: {
        brand_name?: string | null;
        brand_primary_color?: string | null;
        default_user_role?: string | null;
        allow_member_invites?: boolean;
    };
    members: TenantMember[];
    onboarding: {
        completion_rate: number;
        is_complete: boolean;
        items: Array<{
            key: string;
            title: string;
            description: string;
            done: boolean;
        }>;
    };
};

const props = defineProps<{
    tenantDetails: TenantDetails;
    canManageMembers: boolean;
    canManageOnboarding: boolean;
    canManageBilling: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tenants', href: '/tenants' },
    { title: props.tenantDetails.name, href: `/tenants/${props.tenantDetails.slug}` },
];

const form = useForm({
    email: '',
    can_create_tenants: false,
});

function isValidHexColor(color: string | null | undefined): boolean {
    if (!color) {
        return false;
    }

    return /^#[0-9A-Fa-f]{6}$/.test(color.trim());
}

function roleLabel(role: string | null | undefined): string {
    if (role === 'manager') {
        return 'gestor';
    }

    return 'membro';
}

function memberRoleLabel(role: string): string {
    if (role === 'owner') {
        return 'proprietario';
    }

    return roleLabel(role);
}

function addMember(): void {
    form.post(`/tenants/${props.tenantDetails.slug}/members`, {
        preserveScroll: true,
        onSuccess: () => form.reset('email', 'can_create_tenants'),
    });
}

function removeMember(member: TenantMember): void {
    if (!window.confirm(`Remover ${member.name} deste tenant?`)) {
        return;
    }

    router.delete(`/tenants/${props.tenantDetails.slug}/members/${member.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="tenantDetails.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ tenantDetails.name }}</CardTitle>
                    <div class="flex items-center gap-2">
                        <Button v-if="canManageOnboarding" variant="outline" as-child>
                            <Link href="/tenants/onboarding">Wizard inicial</Link>
                        </Button>
                        <Button v-if="canManageBilling" variant="outline" as-child>
                            <Link href="/tenants/billing">Planos e faturação</Link>
                        </Button>
                        <Button variant="outline" as-child>
                            <Link href="/tenants">Voltar</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p class="text-sm text-muted-foreground">Slug: {{ tenantDetails.slug }}</p>
                    <p class="text-sm text-muted-foreground">Proprietario: {{ tenantDetails.owner.name }} ({{ tenantDetails.owner.email }})</p>
                    <p class="text-sm text-muted-foreground">Marca: {{ tenantDetails.settings.brand_name ?? '-' }}</p>
                    <p class="flex items-center gap-2 text-sm text-muted-foreground">
                        Cor principal:
                        <span
                            v-if="isValidHexColor(tenantDetails.settings.brand_primary_color)"
                            class="inline-block size-4 rounded-full border border-black/20"
                            :style="{ backgroundColor: tenantDetails.settings.brand_primary_color! }"
                            :title="tenantDetails.settings.brand_primary_color!"
                        />
                        <span v-else>-</span>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Papel predefinido: {{ roleLabel(tenantDetails.settings.default_user_role) }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Convites de membros: {{ tenantDetails.settings.allow_member_invites ? 'ativos' : 'desativados' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Progresso onboarding: {{ tenantDetails.onboarding.completion_rate }}%
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Utilizadores autorizados</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <form v-if="canManageMembers" class="grid gap-4 md:grid-cols-[1fr_auto_auto]" @submit.prevent="addMember">
                        <div class="grid gap-2">
                            <Label for="email">Email do utilizador</Label>
                            <Input id="email" v-model="form.email" type="email" required />
                            <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                        </div>
                        <label class="flex items-end gap-2 pb-2 text-sm">
                            <input v-model="form.can_create_tenants" type="checkbox" />
                            Pode criar tenants
                        </label>
                        <div class="flex items-end">
                            <Button type="submit" :disabled="form.processing">Autorizar</Button>
                        </div>
                    </form>

                    <div class="space-y-2">
                        <div
                            v-for="member in tenantDetails.members"
                            :key="member.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                        >
                            <div>
                                <p class="font-medium">{{ member.name }}</p>
                                <p class="text-sm text-muted-foreground">{{ member.email }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="secondary">{{ memberRoleLabel(member.role) }}</Badge>
                                <Badge v-if="member.can_create_tenants" variant="outline">pode criar tenants</Badge>
                                <Button
                                    v-if="canManageMembers && member.id !== tenantDetails.owner.id"
                                    size="sm"
                                    variant="destructive"
                                    @click="removeMember(member)"
                                >
                                    Remover
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
