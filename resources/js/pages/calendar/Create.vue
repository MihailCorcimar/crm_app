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

type RelatedSelectOption = SelectOption & {
    entity_id: number | null;
};

type EventableTypeOption = {
    value: 'entity' | 'person' | 'deal';
    label: string;
};

const props = defineProps<{
    owners: SelectOption[];
    eventableTypes: EventableTypeOption[];
    entities: SelectOption[];
    people: RelatedSelectOption[];
    deals: RelatedSelectOption[];
    types: SelectOption[];
    actions: SelectOption[];
    defaults: {
        start_at: string;
        end_at: string;
        status: string;
        owner_id: number | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Calendário', href: '/calendar' },
    { title: 'Agendar atividade', href: '/calendar/create' },
];

const form = useForm({
    title: '',
    description: '',
    start_at: props.defaults.start_at,
    end_at: props.defaults.end_at,
    location: '',
    owner_id: props.defaults.owner_id ?? '',
    eventable_type: '' as '' | 'entity' | 'person' | 'deal',
    eventable_id: '' as number | '',
    calendar_type_id: '' as number | '',
    calendar_action_id: '' as number | '',
    attendee_entity_ids: [] as number[],
    attendee_person_ids: [] as number[],
    attendee_deal_ids: [] as number[],
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
                        :owners="owners"
                        :eventable-types="eventableTypes"
                        :entities="entities"
                        :people="people"
                        :deals="deals"
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
