<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type CompanyPayload = {
    name: string;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    tax_number: string | null;
    logo_url: string | null;
};

const props = defineProps<{
    company: CompanyPayload;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracoes - Empresa', href: '/settings/company' },
];

const form = useForm({
    name: props.company.name ?? '',
    address: props.company.address ?? '',
    postal_code: props.company.postal_code ?? '',
    city: props.company.city ?? '',
    tax_number: props.company.tax_number ?? '',
    logo: null as File | null,
});

function onLogoChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.logo = target.files?.[0] ?? null;
}

function submit(): void {
    form.put('/settings/company', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Configuracoes - Empresa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout :show-system-nav="false">
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Configuracoes - Empresa"
                    description="Dados usados no branding da aplicacao e em documentos PDF."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Dados da Empresa</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Logotipo Empresa</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="onLogoChange"
                                    class="border-input bg-background ring-offset-background file:text-foreground w-full rounded-md border px-3 py-2 text-sm file:mr-4 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                                >
                                <img
                                    v-if="company.logo_url"
                                    :src="company.logo_url"
                                    alt="Company logo"
                                    class="h-16 w-16 rounded border object-contain"
                                >
                                <InputError :message="form.errors.logo" />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium">Nome</label>
                                    <Input v-model="form.name" required />
                                    <InputError :message="form.errors.name" />
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium">Numero Contribuinte</label>
                                    <Input v-model="form.tax_number" placeholder="123456789" />
                                    <InputError :message="form.errors.tax_number" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium">Morada</label>
                                    <Input v-model="form.address" />
                                    <InputError :message="form.errors.address" />
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium">Codigo Postal</label>
                                    <Input v-model="form.postal_code" placeholder="0000-000" />
                                    <InputError :message="form.errors.postal_code" />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Localidade</label>
                                <Input v-model="form.city" />
                                <InputError :message="form.errors.city" />
                            </div>

                            <div class="flex gap-2">
                                <Button type="submit" :disabled="form.processing">Guardar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
