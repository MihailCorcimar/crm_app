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

const props = defineProps<{
    permissionGroups: PermissionGroupOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestao de Acessos - Utilizadores', href: '/access/users' },
    { title: 'Criar utilizador', href: '/access/users/create' },
];

const form = useForm({
    name: '',
    email: '',
    mobile: '',
    permission_group_id: '' as number | '',
    status: 'active',
});

function submit(): void {
    form.post('/access/users');
}
</script>

<template>
    <Head title="Criar utilizador" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Novo utilizador</CardTitle>
                </CardHeader>
                <CardContent>
                    <UserForm
                        :form="form"
                        :permission-groups="permissionGroups"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
