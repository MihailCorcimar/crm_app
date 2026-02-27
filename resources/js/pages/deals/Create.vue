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

const props = defineProps<{
    entities: SelectOption[];
    owners: SelectOption[];
    stageOptions: StageOption[];
    defaultOwnerId: number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: 'Criar negócio', href: '/deals/create' },
];

const form = useForm({
    entity_id: '' as number | '',
    title: '',
    stage: props.stageOptions[0]?.value ?? 'lead',
    value: '0.00',
    probability: '0',
    expected_close_date: '',
    owner_id: props.defaultOwnerId ?? props.owners[0]?.id ?? '',
});

function submit(): void {
    form.post('/deals');
}
</script>

<template>
    <Head title="Criar negócio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Novo negócio</CardTitle>
                </CardHeader>
                <CardContent>
                    <DealForm
                        :form="form"
                        :entities="entities"
                        :owners="owners"
                        :stage-options="stageOptions"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
