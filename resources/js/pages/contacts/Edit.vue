<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ContactForm from '@/components/contacts/ContactForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SelectOption = {
    id: number;
    name: string;
};

type ContactFormPayload = {
    id: number;
    number: number;
    entity_id: number;
    first_name: string;
    last_name: string | null;
    role_id: number;
    phone: string | null;
    mobile: string | null;
    email: string | null;
    gdpr_consent: boolean;
    notes: string | null;
    status: string;
};

const props = defineProps<{
    contact: ContactFormPayload;
    entities: SelectOption[];
    roles: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contactos', href: '/contacts' },
    { title: 'Editar contacto', href: `/contacts/${props.contact.id}/edit` },
];

const form = useForm({
    number: props.contact.number,
    entity_id: props.contact.entity_id,
    first_name: props.contact.first_name,
    last_name: props.contact.last_name ?? '',
    role_id: props.contact.role_id,
    phone: props.contact.phone ?? '',
    mobile: props.contact.mobile ?? '',
    email: props.contact.email ?? '',
    gdpr_consent: props.contact.gdpr_consent,
    notes: props.contact.notes ?? '',
    status: props.contact.status,
});

function submit(): void {
    form.put(`/contacts/${props.contact.id}`);
}
</script>

<template>
    <Head title="Editar contacto" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar contacto</CardTitle>
                </CardHeader>
                <CardContent>
                    <ContactForm
                        :form="form"
                        :entities="entities"
                        :roles="roles"
                        submit-label="Guardar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
