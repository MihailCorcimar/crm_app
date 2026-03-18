<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PermissionGroupForm from '@/components/access/PermissionGroupForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type PermissionAction = 'create' | 'read' | 'update' | 'delete';

type PermissionGroupPayload = {
    id: number;
    name: string;
    status: string;
} & Record<string, unknown>;

const props = defineProps<{
    permissionGroup: PermissionGroupPayload;
    permissionModules: Record<string, string>;
    permissionActions: Record<PermissionAction, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestão de Acessos - Permissões', href: '/access/permission-groups' },
    { title: `Editar ${props.permissionGroup.name}`, href: `/access/permission-groups/${props.permissionGroup.id}/edit` },
];

const form = useForm<Record<string, unknown>>({
    name: props.permissionGroup.name,
    status: props.permissionGroup.status,
    ...Object.keys(props.permissionGroup)
        .filter((key) => key.includes('_'))
        .reduce<Record<string, unknown>>((acc, key) => {
            acc[key] = props.permissionGroup[key];

            return acc;
        }, {}),
});

function submit(): void {
    form.put(`/access/permission-groups/${props.permissionGroup.id}`);
}
</script>

<template>
    <Head title="Editar grupo de permissão" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar grupo de permissão</CardTitle>
                </CardHeader>
                <CardContent>
                    <PermissionGroupForm
                        :form="form"
                        :permission-modules="permissionModules"
                        :permission-actions="permissionActions"
                        submit-label="Guardar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>


