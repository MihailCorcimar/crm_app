<script setup lang="ts">
import DealAutomationRuleForm from '@/components/automations/DealAutomationRuleForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

type RuleDefaults = {
    name: string;
    inactivity_days: number;
    activity_type: 'call' | 'task' | 'meeting' | 'note';
    activity_due_in_days: number;
    activity_priority: 'low' | 'medium' | 'high';
    activity_title_template: string;
    activity_description_template: string;
    notify_internal: boolean;
    notification_message: string;
    status: 'active' | 'paused';
};

const props = defineProps<{
    defaults: RuleDefaults;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Automacoes', href: '/automations/deal-rules' },
    { title: 'Nova regra', href: '/automations/deal-rules/create' },
];

const form = useForm({
    name: props.defaults.name,
    inactivity_days: props.defaults.inactivity_days,
    activity_type: props.defaults.activity_type,
    activity_due_in_days: props.defaults.activity_due_in_days,
    activity_priority: props.defaults.activity_priority,
    activity_title_template: props.defaults.activity_title_template,
    activity_description_template: props.defaults.activity_description_template,
    notify_internal: props.defaults.notify_internal,
    notification_message: props.defaults.notification_message,
    status: props.defaults.status,
});

function submit(): void {
    form.post('/automations/deal-rules');
}
</script>

<template>
    <Head title="Nova regra de automacao" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Nova regra de automacao</CardTitle>
                </CardHeader>
                <CardContent>
                    <DealAutomationRuleForm
                        :form="form"
                        submit-label="Guardar regra"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

