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

type EventableTypeOption = {
    value: 'entity' | 'person' | 'deal';
    label: string;
};

type EventPayload = {
    id: number;
    title: string | null;
    description: string | null;
    start_at: string;
    end_at: string;
    location: string | null;
    owner_id: number | null;
    eventable_type: '' | 'entity' | 'person' | 'deal' | null;
    eventable_id: number | null;
    calendar_type_id: number | null;
    calendar_action_id: number | null;
    attendee_entity_ids: number[];
    attendee_person_ids: number[];
    attendee_deal_ids: number[];
    status: string;
};

const props = defineProps<{
    event: EventPayload;
    owners: SelectOption[];
    eventableTypes: EventableTypeOption[];
    entities: SelectOption[];
    people: SelectOption[];
    deals: SelectOption[];
    types: SelectOption[];
    actions: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendario', href: '/calendar' },
    { title: 'Editar atividade', href: `/calendar/${props.event.id}/edit` },
];

const form = useForm({
    title: props.event.title ?? '',
    description: props.event.description ?? '',
    start_at: props.event.start_at,
    end_at: props.event.end_at,
    location: props.event.location ?? '',
    owner_id: props.event.owner_id ?? '',
    eventable_type: (props.event.eventable_type ?? '') as '' | 'entity' | 'person' | 'deal',
    eventable_id: props.event.eventable_id ?? '',
    calendar_type_id: props.event.calendar_type_id ?? '',
    calendar_action_id: props.event.calendar_action_id ?? '',
    attendee_entity_ids: props.event.attendee_entity_ids ?? [],
    attendee_person_ids: props.event.attendee_person_ids ?? [],
    attendee_deal_ids: props.event.attendee_deal_ids ?? [],
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
                        :owners="owners"
                        :eventable-types="eventableTypes"
                        :entities="entities"
                        :people="people"
                        :deals="deals"
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
