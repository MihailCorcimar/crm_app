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

const props = defineProps<{
    entities: SelectOption[];
    roles: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pessoas', href: '/people' },
    { title: 'Criar pessoa', href: '/people/create' },
];

const form = useForm({
    number: '' as number | '',
    entity_id: props.entities[0]?.id ?? '',
    first_name: '',
    last_name: '',
    role_id: props.roles[0]?.id ?? '',
    phone: '',
    mobile: '',
    email: '',
    gdpr_consent: false,
    notes: '',
    status: 'active',
});

function submit(): void {
    form.post('/people');
}
</script>

<template>
    <Head title="Criar pessoa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Nova pessoa</CardTitle>
                </CardHeader>
                <CardContent>
                    <ContactForm
                        :form="form"
                        :entities="entities"
                        :roles="roles"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
