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

type EventPayload = {
    id: number;
    event_date: string;
    event_time: string;
    duration_minutes: number;
    share: string | null;
    knowledge: string | null;
    entity_id: number | null;
    user_id: number | null;
    calendar_type_id: number | null;
    calendar_action_id: number | null;
    description: string | null;
    status: string;
};

const props = defineProps<{
    event: EventPayload;
    users: SelectOption[];
    entities: SelectOption[];
    types: SelectOption[];
    actions: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendario', href: '/calendar' },
    { title: 'Editar atividade', href: `/calendar/${props.event.id}/edit` },
];

const form = useForm({
    event_date: props.event.event_date,
    event_time: props.event.event_time,
    duration_minutes: props.event.duration_minutes,
    share: props.event.share ?? '',
    knowledge: props.event.knowledge ?? '',
    entity_id: props.event.entity_id ?? '',
    user_id: props.event.user_id ?? '',
    calendar_type_id: props.event.calendar_type_id ?? '',
    calendar_action_id: props.event.calendar_action_id ?? '',
    description: props.event.description ?? '',
    status: props.event.status,
});

function submit(): void {
    form.put(`/calendar/${props.event.id}`);
}
</script>

<template>
    <Head title="Editar atividade" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar atividade</CardTitle>
                </CardHeader>
                <CardContent>
                    <CalendarEventForm
                        :form="form"
                        :users="users"
                        :entities="entities"
                        :types="types"
                        :actions="actions"
                        submit-label="Guardar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
