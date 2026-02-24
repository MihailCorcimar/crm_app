<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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

type VatRateOption = {
    id: number;
    name: string;
    rate: number;
};

type ItemRow = {
    id: number;
    reference: string;
    name: string;
    description: string | null;
    price: number;
    vat_rate_id: number | null;
    vat_rate: string | null;
    vat_rate_value: number;
    photo_url: string | null;
    notes: string | null;
    status: 'active' | 'inactive';
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedItems = {
    data: ItemRow[];
    links: PaginationLink[];
};

const props = defineProps<{
    items: PaginatedItems;
    vatRates: VatRateOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Configuracoes - Artigos', href: '/settings/items' }];

const createForm = useForm({
    reference: '',
    name: '',
    description: '',
    price: '',
    vat_rate_id: props.vatRates[0]?.id ?? '',
    photo: null as File | null,
    notes: '',
    status: 'active',
});

const editForm = useForm({
    id: null as number | null,
    reference: '',
    name: '',
    description: '',
    price: '',
    vat_rate_id: '' as number | '',
    photo: null as File | null,
    notes: '',
    status: 'active',
});

const deletingId = ref<number | null>(null);
const createPhotoName = ref('Nenhuma imagem selecionada');
const editPhotoName = ref('Nenhuma imagem selecionada');

function onCreatePhotoChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    createForm.photo = target.files?.[0] ?? null;
    createPhotoName.value = createForm.photo?.name ?? 'Nenhuma imagem selecionada';
}

function onEditPhotoChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    editForm.photo = target.files?.[0] ?? null;
    editPhotoName.value = editForm.photo?.name ?? 'Nenhuma imagem selecionada';
}

function createItem(): void {
    createForm.post('/settings/items', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            createForm.reset('reference', 'name', 'description', 'price', 'photo', 'notes');
            createPhotoName.value = 'Nenhuma imagem selecionada';
        },
    });
}

function startEdit(item: ItemRow): void {
    editForm.id = item.id;
    editForm.reference = item.reference;
    editForm.name = item.name;
    editForm.description = item.description ?? '';
    editForm.price = String(item.price);
    editForm.vat_rate_id = item.vat_rate_id ?? props.vatRates[0]?.id ?? '';
    editForm.notes = item.notes ?? '';
    editForm.status = item.status;
    editForm.photo = null;
    editPhotoName.value = 'Nenhuma imagem selecionada';
}

function cancelEdit(): void {
    editForm.reset();
    editForm.id = null;
    editPhotoName.value = 'Nenhuma imagem selecionada';
}

function updateItem(): void {
    if (!editForm.id) {
        return;
    }

    editForm.post(`/settings/items/${editForm.id}?_method=PUT`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => cancelEdit(),
    });
}

function destroyItem(item: ItemRow): void {
    if (!window.confirm(`Eliminar artigo ${item.reference}?`)) {
        return;
    }

    deletingId.value = item.id;
    editForm.delete(`/settings/items/${item.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

const columns: ColumnDef<ItemRow>[] = [
    { accessorKey: 'reference', header: 'Referencia' },
    {
        accessorKey: 'photo_url',
        header: 'Foto',
        cell: ({ row }: { row: { original: ItemRow } }) =>
            row.original.photo_url
                ? h('img', { src: row.original.photo_url, alt: row.original.name, class: 'h-10 w-10 rounded object-cover' })
                : '-',
    },
    { accessorKey: 'name', header: 'Nome' },
    { accessorKey: 'description', header: 'Descricao' },
    {
        accessorKey: 'price',
        header: 'Preco',
        cell: ({ row }: { row: { original: ItemRow } }) => `${row.original.price.toFixed(2)} €`,
    },
    {
        id: 'actions',
        header: 'Acoes',
        cell: ({ row }: { row: { original: ItemRow } }) =>
            h('div', { class: 'flex gap-2' }, [
                h(Button, { size: 'sm', variant: 'outline', onClick: () => startEdit(row.original) }, () => 'Editar'),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'destructive',
                        disabled: deletingId.value === row.original.id,
                        onClick: () => destroyItem(row.original),
                    },
                    () => 'Eliminar',
                ),
            ]),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Configuracoes - Artigos" />

        <SettingsLayout wide :show-system-nav="false">
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Configuracoes - Artigos"
                    description="Gerir artigos, precos e IVA."
                />

                <Card>
                    <CardHeader><CardTitle>Novo artigo</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createItem">
                            <Input v-model="createForm.reference" placeholder="Referencia" />
                            <Input v-model="createForm.name" placeholder="Nome" />
                            <Input v-model="createForm.description" placeholder="Descricao" />
                            <Input v-model="createForm.price" type="number" step="0.01" placeholder="Preco" />
                            <select
                                v-model="createForm.vat_rate_id"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option v-for="vatRate in vatRates" :key="vatRate.id" :value="vatRate.id">
                                    {{ vatRate.name }} ({{ vatRate.rate }}%)
                                </option>
                            </select>
                            <select
                                v-model="createForm.status"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                            <div class="flex items-center gap-3">
                                <label
                                    for="create-item-photo"
                                    class="border-input bg-background hover:bg-accent hover:text-accent-foreground inline-flex h-9 cursor-pointer items-center rounded-md border px-3 text-sm shadow-xs"
                                >
                                    Escolha uma imagem
                                </label>
                                <span class="text-muted-foreground max-w-[240px] truncate text-sm">{{ createPhotoName }}</span>
                                <input id="create-item-photo" type="file" accept="image/*" class="hidden" @change="onCreatePhotoChange" />
                            </div>
                            <Input v-model="createForm.notes" placeholder="Observacoes" />
                            <div>
                                <Button type="submit" :disabled="createForm.processing">Adicionar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card v-if="editForm.id">
                    <CardHeader><CardTitle>Editar artigo</CardTitle></CardHeader>
                    <CardContent>
                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="updateItem">
                            <Input v-model="editForm.reference" placeholder="Referencia" />
                            <Input v-model="editForm.name" placeholder="Nome" />
                            <Input v-model="editForm.description" placeholder="Descricao" />
                            <Input v-model="editForm.price" type="number" step="0.01" placeholder="Preco" />
                            <select
                                v-model="editForm.vat_rate_id"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option v-for="vatRate in vatRates" :key="vatRate.id" :value="vatRate.id">
                                    {{ vatRate.name }} ({{ vatRate.rate }}%)
                                </option>
                            </select>
                            <select
                                v-model="editForm.status"
                                class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                            >
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                            </select>
                            <div class="flex items-center gap-3">
                                <label
                                    for="edit-item-photo"
                                    class="border-input bg-background hover:bg-accent hover:text-accent-foreground inline-flex h-9 cursor-pointer items-center rounded-md border px-3 text-sm shadow-xs"
                                >
                                    Escolha uma imagem
                                </label>
                                <span class="text-muted-foreground max-w-[240px] truncate text-sm">{{ editPhotoName }}</span>
                                <input id="edit-item-photo" type="file" accept="image/*" class="hidden" @change="onEditPhotoChange" />
                            </div>
                            <Input v-model="editForm.notes" placeholder="Observacoes" />
                            <div class="flex gap-2">
                                <Button type="submit" :disabled="editForm.processing">Guardar</Button>
                                <Button type="button" variant="outline" @click="cancelEdit">Cancelar</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Artigos</CardTitle></CardHeader>
                    <CardContent class="space-y-4">
                        <DataTable :columns="columns" :data="items.data" empty-text="Sem artigos configurados." />
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-for="(link, index) in items.links"
                                :key="`${link.label}-${index}`"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="!link.url"
                                as-child
                            >
                                <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state><span v-html="link.label" /></Link>
                                <span v-else v-html="link.label" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
