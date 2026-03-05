<script setup lang="ts">
import ItemForm from '@/components/items/ItemForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type VatRateOption = {
    id: number;
    name: string;
    rate: number;
};

type ItemPayload = {
    id: number;
    reference: string;
    code: string;
    name: string;
    description: string | null;
    price: number;
    vat: number;
    vat_rate_id: number | null;
    status: 'active' | 'inactive';
    notes: string | null;
};

const props = defineProps<{
    item: ItemPayload;
    vatRates: VatRateOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produtos', href: '/items' },
    { title: 'Editar produto', href: `/items/${props.item.id}/edit` },
];

const form = useForm({
    reference: props.item.reference,
    code: props.item.code,
    name: props.item.name,
    description: props.item.description ?? '',
    price: String(props.item.price),
    vat: String(props.item.vat),
    vat_rate_id: props.item.vat_rate_id ?? '' as number | '',
    status: props.item.status,
    notes: props.item.notes ?? '',
});

function submit(): void {
    form.put(`/items/${props.item.id}`);
}
</script>

<template>
    <Head title="Editar produto" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Editar produto</CardTitle>
                </CardHeader>
                <CardContent>
                    <ItemForm
                        :form="form"
                        :vat-rates="vatRates"
                        submit-label="Guardar alterações"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

