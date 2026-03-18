<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import DealAutomationRuleForm from '@/components/automations/DealAutomationRuleForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type RulePayload = {
    id: number;
    name: string;
    inactivity_days: number;
    activity_type: 'call' | 'task' | 'meeting' | 'note';
    activity_due_in_days: number;
    activity_priority: 'low' | 'medium' | 'high';
    activity_title_template: string;
    activity_description_template: string | null;
    notify_internal: boolean;
    notification_message: string | null;
    status: 'active' | 'paused';
};

const props = defineProps<{
    rule: RulePayload;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Automações', href: '/automations/deal-rules' },
    { title: 'Editar regra', href: `/automations/deal-rules/${props.rule.id}/edit` },
];

const form = useForm({
    name: props.rule.name,
    inactivity_days: props.rule.inactivity_days,
    activity_type: props.rule.activity_type,
    activity_due_in_days: props.rule.activity_due_in_days,
    activity_priority: props.rule.activity_priority,
    activity_title_template: props.rule.activity_title_template,
    activity_description_template: props.rule.activity_description_template ?? '',
    notify_internal: props.rule.notify_internal,
    notification_message: props.rule.notification_message ?? '',
    status: props.rule.status,
});

function submit(): void {
    form.put(`/automations/deal-rules/${props.rule.id}`);
}
</script>

<template>
    <Head title="Editar regra de automacao" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar regra de automacao</CardTitle>
                </CardHeader>
                <CardContent>
                    <DealAutomationRuleForm
                        :form="form"
                        submit-label="Guardar alteracoes"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

