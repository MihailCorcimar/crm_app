<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    defaults: {
        brand_name: string;
        brand_primary_color: string;
        default_user_role: string;
        allow_member_invites: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tenants', href: '/tenants' },
    { title: 'Criar tenant', href: '/tenants/create' },
];

const form = useForm({
    name: '',
    slug: '',
    settings: {
        brand_name: props.defaults.brand_name,
        brand_primary_color: props.defaults.brand_primary_color,
        default_user_role: props.defaults.default_user_role,
        allow_member_invites: props.defaults.allow_member_invites,
    },
});

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

const slugPreview = computed(() => {
    const value = form.slug.trim() !== '' ? form.slug : form.name;

    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
});

function submit(): void {
    form.post('/tenants');
}

function applyBrandColor(color: string): void {
    form.settings.brand_primary_color = color.toUpperCase();
}

function onBrandColorInput(event: Event): void {
    const value = (event.target as HTMLInputElement | null)?.value;

    if (!value) {
        return;
    }

    applyBrandColor(value);
}
</script>

<template>
    <Head title="Criar tenant" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Criar tenant</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Nome</Label>
                                <Input id="name" v-model="form.name" required />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="slug">Slug</Label>
                                <Input id="slug" v-model="form.slug" placeholder="opcional" />
                                <p class="text-xs text-muted-foreground">{{ slugPreview }}</p>
                                <InputError :message="form.errors.slug" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="brand_name">Nome da marca</Label>
                                <Input id="brand_name" v-model="form.settings.brand_name" />
                                <InputError :message="form.errors['settings.brand_name']" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="brand_primary_color_picker">Cor principal</Label>
                                <div class="flex items-center gap-3">
                                    <input
                                        id="brand_primary_color_picker"
                                        type="color"
                                        :value="form.settings.brand_primary_color"
                                        class="h-10 w-16 cursor-pointer rounded-md border border-input bg-background p-1"
                                        @input="onBrandColorInput"
                                    >
                                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                        <span
                                            class="inline-block size-4 rounded-full border border-black/15"
                                            :style="{ backgroundColor: form.settings.brand_primary_color }"
                                        />
                                        {{ form.settings.brand_primary_color }}
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
                                <p class="text-xs text-muted-foreground">Escolhe no seletor ou nas cores sugeridas.</p>
                                <InputError :message="form.errors['settings.brand_primary_color']" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="default_user_role">Papel do utilizador</Label>
                                <select
                                    id="default_user_role"
                                    v-model="form.settings.default_user_role"
                                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="member">membro</option>
                                    <option value="manager">gestor</option>
                                </select>
                                <InputError :message="form.errors['settings.default_user_role']" />
                            </div>

                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="form.settings.allow_member_invites" type="checkbox" />
                                    Permitir que membros convidem utilizadores
                                </label>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <Button type="submit" :disabled="form.processing">Criar</Button>
                            <Button type="button" variant="outline" as-child>
                                <Link href="/tenants">Cancelar</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
