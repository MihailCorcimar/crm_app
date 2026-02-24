<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import CalendarEventForm from '@/components/calendar/CalendarEventForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SelectOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    users: SelectOption[];
    entities: SelectOption[];
    types: SelectOption[];
    actions: SelectOption[];
    defaults: {
        event_date: string;
        event_time: string;
        duration_minutes: number;
        status: string;
        user_id: number | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendario', href: '/calendar' },
    { title: 'Agendar atividade', href: '/calendar/create' },
];

const form = useForm({
    event_date: props.defaults.event_date,
    event_time: props.defaults.event_time,
    duration_minutes: props.defaults.duration_minutes,
    share: '',
    knowledge: '',
    entity_id: '' as number | '',
    user_id: props.defaults.user_id ?? '',
    calendar_type_id: '' as number | '',
    calendar_action_id: '' as number | '',
    description: '',
    status: props.defaults.status,
});

function submit(): void {
    form.post('/calendar');
}
</script>

<template>
    <Head title="Agendar atividade" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Nova atividade</CardTitle>
                </CardHeader>
                <CardContent>
                    <CalendarEventForm
                        :form="form"
                        :users="users"
                        :entities="entities"
                        :types="types"
                        :actions="actions"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
