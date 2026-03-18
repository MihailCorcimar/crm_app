<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import LeadFormForm from '@/components/lead-forms/LeadFormForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type FieldSchemaRow = {
    key: string;
    label: string;
    type: 'text' | 'email' | 'tel' | 'textarea' | 'number' | 'date' | 'select' | 'checkbox';
    enabled: boolean;
    required: boolean;
    is_system?: boolean;
    options?: string[];
};

type ConversionSettings = {
    create_deal: boolean;
    entity_name_field_key: string | null;
    deal_title_field_key: string | null;
    deal_title_template: string;
    deal_value_field_key: string | null;
    deal_stage: string;
    deal_owner_id: number | null;
    deal_probability: number;
};

const props = defineProps<{
    defaults: {
        name: string;
        slug: string;
        status: 'active' | 'inactive';
        requires_captcha: boolean;
        confirmation_message: string;
        field_schema: FieldSchemaRow[];
        conversion_settings: ConversionSettings;
    };
    ownerOptions: Array<{ id: number; name: string }>;
    stageOptions: Array<{ value: string; label: string }>;
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
    conversion_settings: props.defaults.conversion_settings,
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
                        :owner-options="ownerOptions"
                        :stage-options="stageOptions"
                        submit-label="Guardar formulario"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

