<script setup lang="ts">
import LeadFormForm from '@/components/lead-forms/LeadFormForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

type FieldSchemaRow = {
    key: 'full_name' | 'email' | 'phone' | 'company' | 'message';
    label: string;
    type: 'text' | 'email' | 'tel' | 'textarea';
    enabled: boolean;
    required: boolean;
};

const props = defineProps<{
    leadForm: {
        id: number;
        name: string;
        slug: string;
        status: 'active' | 'inactive';
        requires_captcha: boolean;
        confirmation_message: string;
        field_schema: FieldSchemaRow[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formularios publicos', href: '/lead-forms' },
    { title: 'Editar formulario', href: `/lead-forms/${props.leadForm.id}/edit` },
];

const form = useForm({
    name: props.leadForm.name,
    slug: props.leadForm.slug,
    status: props.leadForm.status,
    requires_captcha: props.leadForm.requires_captcha,
    confirmation_message: props.leadForm.confirmation_message,
    field_schema: props.leadForm.field_schema,
});

function submit(): void {
    form.put(`/lead-forms/${props.leadForm.id}`);
}
</script>

<template>
    <Head title="Editar formulario publico" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar formulario publico</CardTitle>
                </CardHeader>
                <CardContent>
                    <LeadFormForm
                        :form="form"
                        submit-label="Guardar alteracoes"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

