<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type DealStageRow = {
    value: string;
    label: string;
    order: number;
};

const props = defineProps<{
    stages: DealStageRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações - Negócios - Etapas', href: '/settings/deals/stages' },
];

const form = useForm({
    stages: props.stages.map((stage) => ({
        value: stage.value,
        label: stage.label,
        order: stage.order,
    })),
});

function submit(): void {
    form.put('/settings/deals/stages', {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Configurações - Negócios - Etapas" />

        <SettingsLayout :show-system-nav="false">
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Configurações - Negócios - Etapas"
                    description="Define os nomes e a ordem das etapas do Kanban."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Etapas do pipeline</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div
                                v-for="(stage, index) in form.stages"
                                :key="stage.value"
                                class="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_2fr_140px]"
                            >
                                <div class="grid gap-1">
                                    <label class="text-sm font-medium">Identificador</label>
                                    <Input :model-value="stage.value" disabled />
                                </div>

                                <div class="grid gap-1">
                                    <label class="text-sm font-medium">Nome visível</label>
                                    <Input v-model="stage.label" maxlength="50" />
                                </div>

                                <div class="grid gap-1">
                                    <label class="text-sm font-medium">Ordem</label>
                                    <Input v-model.number="stage.order" type="number" min="1" max="999" />
                                </div>

                                <InputError :message="form.errors[`stages.${index}.label`]" />
                                <InputError :message="form.errors[`stages.${index}.order`]" />
                            </div>

                            <InputError :message="form.errors.stages" />

                            <div class="flex justify-end">
                                <Button type="submit" :disabled="form.processing">Guardar configuração</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
