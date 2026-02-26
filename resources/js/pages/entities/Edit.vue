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

type EntityFormPayload = {
    id: number;
    type: string;
    vat: string;
    name: string;
    phone: string | null;
    mobile: string | null;
    website: string | null;
    email: string | null;
    status: string;
    address: string | null;
    postal_code: string | null;
    city: string | null;
    country_id: number;
    notes: string | null;
    gdpr_consent: boolean;
};

const props = defineProps<{
    entity: EntityFormPayload;
    countries: CountryOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Entidades', href: '/entities' },
    { title: 'Editar entidade', href: `/entities/${props.entity.id}/edit` },
];

const form = useForm({
    type: props.entity.type,
    vat: props.entity.vat,
    name: props.entity.name,
    phone: props.entity.phone ?? '',
    mobile: props.entity.mobile ?? '',
    website: props.entity.website ?? '',
    email: props.entity.email ?? '',
    status: props.entity.status,
    address: props.entity.address ?? '',
    postal_code: props.entity.postal_code ?? '',
    city: props.entity.city ?? '',
    country_id: props.entity.country_id,
    notes: props.entity.notes ?? '',
    gdpr_consent: props.entity.gdpr_consent,
});

function submit(): void {
    form.put(`/entities/${props.entity.id}`);
}
</script>

<template>
    <Head title="Editar entidade" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar entidade</CardTitle>
                </CardHeader>
                <CardContent>
                    <EntityForm
                        :form="form"
                        :countries="countries"
                        submit-label="Guardar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
