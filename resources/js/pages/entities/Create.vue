<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import EntityForm from '@/components/entities/EntityForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type CountryOption = {
    id: number;
    code: string;
    name: string;
};

const props = defineProps<{
    defaultType: 'customer' | 'supplier' | 'both';
    countries: CountryOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Entidades', href: '/entities' },
    { title: 'Criar entidade', href: '/entities/create' },
];

const form = useForm({
    type: props.defaultType,
    vat: '',
    name: '',
    phone: '',
    mobile: '',
    website: '',
    email: '',
    status: 'active',
    address: '',
    postal_code: '',
    city: '',
    country_id: props.countries[0]?.id ?? '',
    notes: '',
    gdpr_consent: false,
});

function submit(): void {
    form.post('/entities');
}
</script>

<template>
    <Head title="Criar entidade" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Nova entidade</CardTitle>
                </CardHeader>
                <CardContent>
                    <EntityForm
                        :form="form"
                        :countries="countries"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
