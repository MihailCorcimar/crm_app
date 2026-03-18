<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

type ChecklistItem = {
    key: string;
    done: boolean;
};

type TenantOnboarding = {
    id: number;
    name: string;
    slug: string;
    owner: {
        id: number;
        name: string;
        email: string;
    };
    members: TenantMember[];
};

const props = defineProps<{
    tenantDetails: TenantOnboarding;
    settings: {
        brand_name: string;
        brand_primary_color: string;
        default_user_role: 'member' | 'manager';
        allow_member_invites: boolean;
    };
    checklist: {
        completion_rate: number;
        is_complete: boolean;
        items: ChecklistItem[];
    };
    canManageOnboarding: boolean;
    canManageMembers: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tenants', href: '/tenants' },
    { title: 'Wizard inicial', href: '/tenants/onboarding' },
];

const step = ref<'branding' | 'users' | 'permissions' | 'checklist'>('branding');

const brandingForm = useForm({
    brand_name: props.settings.brand_name,
    brand_primary_color: props.settings.brand_primary_color,
});

const memberForm = useForm({
    email: '',
    can_create_tenants: false,
});

const permissionsForm = useForm({
    default_user_role: props.settings.default_user_role,
    allow_member_invites: props.settings.allow_member_invites,
});

const orderedSteps: Array<'branding' | 'users' | 'permissions' | 'checklist'> = [
    'branding',
    'users',
    'permissions',
    'checklist',
];

const brandColorOptions = [
    '#1F2937',
    '#0EA5E9',
    '#10B981',
    '#F97316',
    '#EF4444',
    '#8B5CF6',
    '#14B8A6',
    '#EAB308',
];

const stepTitle = computed(() => {
    if (step.value === 'branding') return 'Passo 1: Marca';
    if (step.value === 'users') return 'Passo 2: Utilizadores';
    if (step.value === 'permissions') return 'Passo 3: Permissoes';

    return 'Passo 4: Checklist';
});

const checklistLabels: Record<string, { title: string; description: string }> = {
    branding: {
        title: 'Marca',
        description: 'Definir nome da marca e cor principal do tenant.',
    },
    users: {
        title: 'Utilizadores',
        description: 'Autorizar pelo menos mais um utilizador neste tenant.',
    },
    permissions: {
        title: 'Permissoes',
        description: 'Rever papel predefinido e convites de membros.',
    },
    company_profile: {
        title: 'Perfil da empresa',
        description: 'Definir um nome de empresa especifico para este tenant.',
    },
    base_data: {
        title: 'Dados base',
        description: 'Validar dados de setup base (paises, funcoes, IVA e calendario).',
    },
};

function checklistLabel(key: string): { title: string; description: string } {
    return checklistLabels[key] ?? {
        title: key,
        description: '',
    };
}

function nextStep(): void {
    const index = orderedSteps.indexOf(step.value);
    if (index === -1 || index === orderedSteps.length - 1) {
        return;
    }

    step.value = orderedSteps[index + 1];
}

function previousStep(): void {
    const index = orderedSteps.indexOf(step.value);
    if (index <= 0) {
        return;
    }

    step.value = orderedSteps[index - 1];
}

function saveBranding(): void {
    brandingForm.put('/tenants/onboarding/branding', {
        preserveScroll: true,
        onSuccess: () => nextStep(),
    });
}

function applyBrandColor(color: string): void {
    brandingForm.brand_primary_color = color.toUpperCase();
}

function onBrandColorInput(event: Event): void {
    const value = (event.target as HTMLInputElement | null)?.value;

    if (!value) {
        return;
    }

    applyBrandColor(value);
}

function addMember(): void {
    memberForm.post('/tenants/onboarding/members', {
        preserveScroll: true,
        onSuccess: () => memberForm.reset('email', 'can_create_tenants'),
    });
}

function savePermissions(): void {
    permissionsForm.put('/tenants/onboarding/permissions', {
        preserveScroll: true,
        onSuccess: () => nextStep(),
    });
}
</script>

<template>
    <Head title="Onboarding do tenant" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between gap-3">
                    <div>
                        <CardTitle>Onboarding do tenant</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Tenant ativo: <span class="font-medium text-foreground">{{ tenantDetails.name }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge :variant="checklist.is_complete ? 'default' : 'secondary'">
                            {{ checklist.completion_rate }}% concluído
                        </Badge>
                        <Button variant="outline" as-child>
                            <Link href="/tenants">Voltar</Link>
                        </Button>
                    </div>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ stepTitle }}</CardTitle>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <Badge :variant="step === 'branding' ? 'default' : 'outline'">Marca</Badge>
                        <Badge :variant="step === 'users' ? 'default' : 'outline'">Utilizadores</Badge>
                        <Badge :variant="step === 'permissions' ? 'default' : 'outline'">Permissoes</Badge>
                        <Badge :variant="step === 'checklist' ? 'default' : 'outline'">Checklist</Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4">
                    <form
                        v-if="step === 'branding'"
                        class="grid gap-4 md:grid-cols-2"
                        @submit.prevent="saveBranding"
                    >
                        <div class="grid gap-2">
                            <Label for="brand_name">Nome da marca</Label>
                            <Input id="brand_name" v-model="brandingForm.brand_name" required />
                            <p v-if="brandingForm.errors.brand_name" class="text-sm text-destructive">{{ brandingForm.errors.brand_name }}</p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="brand_primary_color_picker">Cor principal</Label>
                            <div class="flex items-center gap-3">
                                <input
                                    id="brand_primary_color_picker"
                                    type="color"
                                    :value="brandingForm.brand_primary_color"
                                    class="h-10 w-16 cursor-pointer rounded-md border border-input bg-background p-1"
                                    @input="onBrandColorInput"
                                >
                                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <span
                                        class="inline-block size-4 rounded-full border border-black/15"
                                        :style="{ backgroundColor: brandingForm.brand_primary_color }"
                                    />
                                    {{ brandingForm.brand_primary_color }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="color in brandColorOptions"
                                    :key="color"
                                    type="button"
                                    class="size-7 rounded-full border border-black/20 ring-offset-background transition hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    :style="{ backgroundColor: color }"
                                    :title="`Escolher ${color}`"
                                    @click="applyBrandColor(color)"
                                ></button>
                            </div>
                            <p class="text-xs text-muted-foreground">Escolhe no seletor ou numa das cores sugeridas.</p>
                            <p v-if="brandingForm.errors.brand_primary_color" class="text-sm text-destructive">{{ brandingForm.errors.brand_primary_color }}</p>
                        </div>

                        <div class="md:col-span-2 flex items-center justify-end gap-2">
                            <Button type="button" variant="outline" @click="nextStep">Ignorar</Button>
                            <Button type="submit" :disabled="brandingForm.processing || !canManageOnboarding">Guardar marca</Button>
                        </div>
                    </form>

                    <div v-else-if="step === 'users'" class="space-y-4">
                        <form
                            v-if="canManageMembers"
                            class="grid gap-4 md:grid-cols-[1fr_auto_auto]"
                            @submit.prevent="addMember"
                        >
                            <div class="grid gap-2">
                                <Label for="member_email">Email do utilizador</Label>
                                <Input id="member_email" v-model="memberForm.email" type="email" required />
                                <p v-if="memberForm.errors.email" class="text-sm text-destructive">{{ memberForm.errors.email }}</p>
                            </div>
                            <label class="flex items-end gap-2 pb-2 text-sm">
                                <input v-model="memberForm.can_create_tenants" type="checkbox">
                                Pode criar tenants
                            </label>
                            <div class="flex items-end">
                                <Button type="submit" :disabled="memberForm.processing">Autorizar</Button>
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
                                    <Badge variant="secondary">{{ member.role }}</Badge>
                                    <Badge v-if="member.can_create_tenants" variant="outline">pode criar tenants</Badge>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <Button variant="outline" @click="previousStep">Voltar</Button>
                            <Button variant="outline" @click="nextStep">Continuar</Button>
                        </div>
                    </div>

                    <form
                        v-else-if="step === 'permissions'"
                        class="space-y-4"
                        @submit.prevent="savePermissions"
                    >
                        <div class="grid gap-2 md:max-w-sm">
                            <Label for="default_user_role">Papel predefinido</Label>
                            <select
                                id="default_user_role"
                                v-model="permissionsForm.default_user_role"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                :disabled="!canManageOnboarding"
                            >
                                <option value="member">membro</option>
                                <option value="manager">gestor</option>
                            </select>
                            <p v-if="permissionsForm.errors.default_user_role" class="text-sm text-destructive">{{ permissionsForm.errors.default_user_role }}</p>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="permissionsForm.allow_member_invites"
                                type="checkbox"
                                :disabled="!canManageOnboarding"
                            >
                            Permitir convites de membros
                        </label>

                        <div class="flex justify-between">
                            <Button type="button" variant="outline" @click="previousStep">Voltar</Button>
                            <Button type="submit" :disabled="permissionsForm.processing || !canManageOnboarding">Guardar permissoes</Button>
                        </div>
                    </form>

                    <div v-else class="space-y-4">
                        <div
                            v-for="item in checklist.items"
                            :key="item.key"
                            class="flex items-start justify-between gap-3 rounded-md border p-3"
                        >
                            <div>
                                <p class="font-medium">{{ checklistLabel(item.key).title }}</p>
                                <p class="text-sm text-muted-foreground">{{ checklistLabel(item.key).description }}</p>
                            </div>
                            <Badge :variant="item.done ? 'default' : 'secondary'">
                                {{ item.done ? 'Concluído' : 'Pendente' }}
                            </Badge>
                        </div>

                        <div class="flex justify-between">
                            <Button variant="outline" @click="previousStep">Voltar</Button>
                            <Button as-child>
                                <Link href="/dashboard">Concluir onboarding</Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
