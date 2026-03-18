<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import DealAutomationRuleForm from '@/components/automations/DealAutomationRuleForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

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
    { title: 'Automações', href: '/automations/deal-rules' },
    { title: 'Nova regra', href: '/automations/deal-rules/create' },
];

const safeDefaults = computed<RuleDefaults>(() => props.defaults ?? {
    name: 'Regra de inatividade',
    inactivity_days: 7,
    activity_type: 'task',
    activity_due_in_days: 0,
    activity_priority: 'medium',
    activity_title_template: 'Follow up automático - {deal_title}',
    activity_description_template: 'Negócio sem atividade há {days_without_activity} dias.',
    notify_internal: true,
    notification_message: 'Foi criada uma nova atividade automática para {deal_title}.',
    status: 'active',
});

const form = useForm({
    name: safeDefaults.value.name,
    inactivity_days: safeDefaults.value.inactivity_days,
    activity_type: safeDefaults.value.activity_type,
    activity_due_in_days: safeDefaults.value.activity_due_in_days,
    activity_priority: safeDefaults.value.activity_priority,
    activity_title_template: safeDefaults.value.activity_title_template,
    activity_description_template: safeDefaults.value.activity_description_template,
    notify_internal: safeDefaults.value.notify_internal,
    notification_message: safeDefaults.value.notification_message,
    status: safeDefaults.value.status,
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
