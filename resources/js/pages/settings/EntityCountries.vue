<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type CountryRow = {
    id: number;
    code: string;
    name: string;
    entities_count: number;
};

const props = defineProps<{
    countries: CountryRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracoes - Paises', href: '/settings/entities/countries' },
];

const createForm = useForm({
    code: '',
    name: '',
});

const editForm = useForm({
    id: null as number | null,
    code: '',
    name: '',
});

const deletingId = ref<number | null>(null);

function createCountry(): void {
    createForm.post('/settings/entities/countries', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function startEdit(country: CountryRow): void {
    editForm.id = country.id;
    editForm.code = country.code;
    editForm.name = country.name;
    editForm.clearErrors();
}

function cancelEdit(): void {
    editForm.reset();
    editForm.id = null;
}

function updateCountry(): void {
    if (!editForm.id) {
        return;
    }

    editForm.put(`/settings/entities/countries/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function deleteCountry(country: CountryRow): void {
    if (!window.confirm(`Eliminar pais ${country.name}?`)) {
        return;
    }

    deletingId.value = country.id;
    editForm.delete(`/settings/entities/countries/${country.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

const columns: ColumnDef<CountryRow>[] = [
    {
        accessorKey: 'code',
        header: 'Codigo',
    },
    {
        accessorKey: 'name',
        header: 'Nome',
    },
    {
        accessorKey: 'entities_count',
        header: 'Entidades',
    },
    {
        id: 'actions',
        header: 'Acoes',
        cell: ({ row }: { row: { original: CountryRow } }) =>
            h('div', { class: 'flex gap-2' }, [
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        onClick: () => startEdit(row.original),
                    },
                    () => 'Editar',
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'destructive',
                        disabled: deletingId.value === row.original.id,
                        onClick: () => deleteCountry(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Configuracoes - Paises" />

        <SettingsLayout :show-system-nav="false">
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Configuracoes - Entidades - Paises"
                    description="Gerir a lista de paises usada nas Entidades."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Novo pais</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="createCountry">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Codigo</label>
                                <Input v-model="createForm.code" maxlength="2" placeholder="PT" />
                                <InputError :message="createForm.errors.code" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <label class="text-sm font-medium">Nome</label>
                                <Input v-model="createForm.name" placeholder="Portugal" />
                                <InputError :message="createForm.errors.name" />
                            </div>
                            <div>
                                <Button type="submit" :disabled="createForm.processing">Adicionar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card v-if="editForm.id">
                    <CardHeader>
                        <CardTitle>Editar pais</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-3" @submit.prevent="updateCountry">
                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Codigo</label>
                                <Input v-model="editForm.code" maxlength="2" />
                                <InputError :message="editForm.errors.code" />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <label class="text-sm font-medium">Nome</label>
                                <Input v-model="editForm.name" />
                                <InputError :message="editForm.errors.name" />
                            </div>
                            <div class="flex gap-2">
                                <Button type="submit" :disabled="editForm.processing">Guardar</Button>
                                <Button type="button" variant="outline" @click="cancelEdit">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Paises</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <DataTable :columns="columns" :data="countries" empty-text="Sem paises configurados." />
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
