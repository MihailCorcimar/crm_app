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
    tax_id: string;
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

const listingHref =
    props.entity.type === 'supplier'
        ? '/entities?type=supplier'
        : props.entity.type === 'both'
          ? '/entities?type=both'
          : '/entities?type=customer';

const sectionTitle =
    props.entity.type === 'supplier'
        ? 'Fornecedores'
        : props.entity.type === 'both'
          ? 'Entidades'
          : 'Clientes';

const breadcrumbs: BreadcrumbItem[] = [
    { title: sectionTitle, href: listingHref },
    { title: 'Editar entidade', href: `/entities/${props.entity.id}/edit` },
];

const form = useForm({
    type: props.entity.type,
    tax_id: props.entity.tax_id,
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
