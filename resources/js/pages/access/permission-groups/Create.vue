<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PermissionGroupForm from '@/components/access/PermissionGroupForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestao de Acessos - Permissoes', href: '/access/permission-groups' },
    { title: 'Criar grupo', href: '/access/permission-groups/create' },
];

const form = useForm({
    name: '',
    menu_a_create: false,
    menu_a_read: true,
    menu_a_update: false,
    menu_a_delete: false,
    menu_b_create: false,
    menu_b_read: true,
    menu_b_update: false,
    menu_b_delete: false,
    status: 'active',
});

function submit(): void {
    form.post('/access/permission-groups');
}
</script>

<template>
    <Head title="Criar grupo de permissao" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Novo grupo de permissao</CardTitle>
                </CardHeader>
                <CardContent>
                    <PermissionGroupForm
                        :form="form"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
