<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PermissionGroupForm from '@/components/access/PermissionGroupForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type PermissionGroupPayload = {
    id: number;
    name: string;
    menu_a_create: boolean;
    menu_a_read: boolean;
    menu_a_update: boolean;
    menu_a_delete: boolean;
    menu_b_create: boolean;
    menu_b_read: boolean;
    menu_b_update: boolean;
    menu_b_delete: boolean;
    status: string;
};

const props = defineProps<{
    permissionGroup: PermissionGroupPayload;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestao de Acessos - Permissoes', href: '/access/permission-groups' },
    { title: `Editar ${props.permissionGroup.name}`, href: `/access/permission-groups/${props.permissionGroup.id}/edit` },
];

const form = useForm({
    name: props.permissionGroup.name,
    menu_a_create: props.permissionGroup.menu_a_create,
    menu_a_read: props.permissionGroup.menu_a_read,
    menu_a_update: props.permissionGroup.menu_a_update,
    menu_a_delete: props.permissionGroup.menu_a_delete,
    menu_b_create: props.permissionGroup.menu_b_create,
    menu_b_read: props.permissionGroup.menu_b_read,
    menu_b_update: props.permissionGroup.menu_b_update,
    menu_b_delete: props.permissionGroup.menu_b_delete,
    status: props.permissionGroup.status,
});

function submit(): void {
    form.put(`/access/permission-groups/${props.permissionGroup.id}`);
}
</script>

<template>
    <Head title="Editar grupo de permissao" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar grupo de permissao</CardTitle>
                </CardHeader>
                <CardContent>
                    <PermissionGroupForm
                        :form="form"
                        submit-label="Guardar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
