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
    defaults: {
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
    { title: 'Criar formulario', href: '/lead-forms/create' },
];

const form = useForm({
    name: props.defaults.name,
    slug: props.defaults.slug,
    status: props.defaults.status,
    requires_captcha: props.defaults.requires_captcha,
    confirmation_message: props.defaults.confirmation_message,
    field_schema: props.defaults.field_schema,
});

function submit(): void {
    form.post('/lead-forms');
}
</script>

<template>
    <Head title="Criar formulario publico" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Novo formulario publico</CardTitle>
                </CardHeader>
                <CardContent>
                    <LeadFormForm
                        :form="form"
                        submit-label="Guardar formulario"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

