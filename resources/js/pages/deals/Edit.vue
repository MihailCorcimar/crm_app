<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import DealForm from '@/components/deals/DealForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type SelectOption = {
    id: number;
    name: string;
};

type StageOption = {
    value: string;
    label: string;
};

type DealFormPayload = {
    id: number;
    entity_id: number | null;
    title: string;
    stage: string;
    value: number;
    probability: number;
    expected_close_date: string | null;
    owner_id: number;
};

const props = defineProps<{
    deal: DealFormPayload;
    entities: SelectOption[];
    owners: SelectOption[];
    stageOptions: StageOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: 'Editar negócio', href: `/deals/${props.deal.id}/edit` },
];

const form = useForm({
    entity_id: props.deal.entity_id ?? '',
    title: props.deal.title,
    stage: props.deal.stage,
    value: props.deal.value.toFixed(2),
    probability: String(props.deal.probability),
    expected_close_date: props.deal.expected_close_date ?? '',
    owner_id: props.deal.owner_id,
});

function submit(): void {
    form.put(`/deals/${props.deal.id}`);
}
</script>

<template>
    <Head title="Editar negócio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar negócio</CardTitle>
                </CardHeader>
                <CardContent>
                    <DealForm
                        :form="form"
                        :entities="entities"
                        :owners="owners"
                        :stage-options="stageOptions"
                        submit-label="Guardar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
