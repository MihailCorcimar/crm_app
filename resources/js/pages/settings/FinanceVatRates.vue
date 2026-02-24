<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { type ColumnDef } from '@tanstack/vue-table';
import { h, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

type VatRateRow = {
    id: number;
    name: string;
    rate: number;
    status: 'active' | 'inactive';
};

const props = defineProps<{
    vatRates: VatRateRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracoes - Financeiro - IVA', href: '/settings/finance/vat-rates' },
];

const createForm = useForm({
    name: '',
    rate: '',
    status: 'active',
});

const editForm = useForm({
    id: null as number | null,
    name: '',
    rate: '',
    status: 'active',
});

const deletingId = ref<number | null>(null);

function createVatRate(): void {
    createForm.post('/settings/finance/vat-rates', {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name', 'rate'),
    });
}

function startEdit(vatRate: VatRateRow): void {
    editForm.id = vatRate.id;
    editForm.name = vatRate.name;
    editForm.rate = String(vatRate.rate);
    editForm.status = vatRate.status;
}

function cancelEdit(): void {
    editForm.reset();
    editForm.id = null;
}

function updateVatRate(): void {
    if (!editForm.id) {
        return;
    }

    editForm.put(`/settings/finance/vat-rates/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function destroyVatRate(vatRate: VatRateRow): void {
    if (!window.confirm(`Eliminar IVA ${vatRate.name}?`)) {
        return;
    }

    deletingId.value = vatRate.id;
    editForm.delete(`/settings/finance/vat-rates/${vatRate.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

const columns: ColumnDef<VatRateRow>[] = [
    { accessorKey: 'name', header: 'Nome' },
    {
        accessorKey: 'rate',
        header: 'Taxa (%)',
        cell: ({ row }: { row: { original: VatRateRow } }) => `${row.original.rate}%`,
    },
    {
        accessorKey: 'status',
        header: 'Estado',
        cell: ({ row }: { row: { original: VatRateRow } }) =>
            row.original.status === 'active' ? 'Ativo' : 'Inativo',
    },
    {
        id: 'actions',
        header: 'Acoes',
        cell: ({ row }: { row: { original: VatRateRow } }) =>
            h('div', { class: 'flex gap-2' }, [
                h(Button, { size: 'sm', variant: 'outline', onClick: () => startEdit(row.original) }, () => 'Editar'),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'destructive',
                        disabled: deletingId.value === row.original.id,
                        onClick: () => destroyVatRate(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Configuracoes - Financeiro - IVA" />

        <SettingsLayout :show-system-nav="false">
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Configuracoes - Financeiro - IVA"
                    description="Gerir as taxas de IVA em percentagem."
                />

                <Card>
                    <CardHeader><CardTitle>Novo IVA</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-4" @submit.prevent="createVatRate">
                            <Input v-model="createForm.name" placeholder="Taxa normal" />
                            <Input v-model="createForm.rate" type="number" step="0.01" placeholder="23" />
                            <select
                                v-model="createForm.status"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                            <Button type="submit" :disabled="createForm.processing">Adicionar</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card v-if="editForm.id">
                    <CardHeader><CardTitle>Editar IVA</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-4" @submit.prevent="updateVatRate">
                            <Input v-model="editForm.name" />
                            <Input v-model="editForm.rate" type="number" step="0.01" />
                            <select
                                v-model="editForm.status"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                            <div class="flex gap-2">
                                <Button type="submit" :disabled="editForm.processing">Guardar</Button>
                                <Button type="button" variant="outline" @click="cancelEdit">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Taxas de IVA</CardTitle></CardHeader>
                    <CardContent>
                        <DataTable :columns="columns" :data="vatRates" empty-text="Sem taxas configuradas." />
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
