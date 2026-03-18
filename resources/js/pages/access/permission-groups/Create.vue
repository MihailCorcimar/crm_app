<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PermissionGroupForm from '@/components/access/PermissionGroupForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type PermissionAction = 'create' | 'read' | 'update' | 'delete';

const props = defineProps<{
    permissionModules: Record<string, string>;
    permissionActions: Record<PermissionAction, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestão de Acessos - Permissões', href: '/access/permission-groups' },
    { title: 'Criar grupo', href: '/access/permission-groups/create' },
];

const basePermissions = Object.keys(props.permissionModules).reduce<Record<string, boolean>>((acc, module) => {
    for (const action of Object.keys(props.permissionActions) as PermissionAction[]) {
        acc[`${module}_${action}`] = action === 'read';
    }

    return acc;
}, {});

const form = useForm<Record<string, unknown>>({
    name: '',
    status: 'active',
    ...basePermissions,
});

function submit(): void {
    form.post('/access/permission-groups');
}
</script>

<template>
    <Head title="Criar grupo de permissão" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Novo grupo de permissão</CardTitle>
                </CardHeader>
                <CardContent>
                    <PermissionGroupForm
                        :form="form"
                        :permission-modules="permissionModules"
                        :permission-actions="permissionActions"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>


