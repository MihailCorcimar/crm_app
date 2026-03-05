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

const props = defineProps<{
    vatRates: VatRateOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Produtos', href: '/items' },
    { title: 'Criar produto', href: '/items/create' },
];

const form = useForm({
    reference: '',
    code: '',
    name: '',
    description: '',
    price: '',
    vat: '23',
    vat_rate_id: '' as number | '',
    status: 'active' as 'active' | 'inactive',
    notes: '',
});

function submit(): void {
    form.post('/items');
}
</script>

<template>
    <Head title="Criar produto" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Novo produto</CardTitle>
                </CardHeader>
                <CardContent>
                    <ItemForm
                        :form="form"
                        :vat-rates="vatRates"
                        submit-label="Guardar produto"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

