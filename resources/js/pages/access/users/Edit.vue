<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import UserForm from '@/components/access/UserForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type PermissionGroupOption = {
    id: number;
    name: string;
};

type UserPayload = {
    id: number;
    name: string;
    email: string;
    mobile: string | null;
    permission_group_id: number | null;
    status: string;
};

const props = defineProps<{
    user: UserPayload;
    permissionGroups: PermissionGroupOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestao de Acessos - Utilizadores', href: '/access/users' },
    { title: `Editar ${props.user.name}`, href: `/access/users/${props.user.id}/edit` },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    mobile: props.user.mobile ?? '',
    permission_group_id: props.user.permission_group_id ?? '',
    status: props.user.status,
});

function submit(): void {
    form.put(`/access/users/${props.user.id}`);
}
</script>

<template>
    <Head title="Editar utilizador" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar utilizador</CardTitle>
                </CardHeader>
                <CardContent>
                    <UserForm
                        :form="form"
                        :permission-groups="permissionGroups"
                        submit-label="Guardar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
