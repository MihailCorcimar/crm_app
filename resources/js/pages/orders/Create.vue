<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import OrderForm from '@/components/orders/OrderForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type CustomerOption = { id: number; name: string };
type SupplierOption = { id: number; name: string };
type ItemOption = { id: number; reference: string; name: string; description: string | null; price: number };

const props = defineProps<{
    customers: CustomerOption[];
    suppliers: SupplierOption[];
    items: ItemOption[];
    defaults: { order_date: string; valid_until: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Encomendas', href: '/orders' },
    { title: 'Criar encomenda', href: '/orders/create' },
];

const form = useForm({
    order_date: props.defaults.order_date,
    valid_until: props.defaults.valid_until,
    customer_id: props.customers[0]?.id ?? '',
    status: 'draft' as 'draft' | 'closed',
    lines: [
        { item_id: '', supplier_id: '', quantity: 1, sale_price: 0, cost_price: 0, search: '' },
    ],
});

function submit(): void {
    form.post('/orders');
}
</script>

<template>
    <Head title="Criar encomenda" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader><CardTitle>Nova encomenda</CardTitle></CardHeader>
                <CardContent>
                    <OrderForm
                        :form="form"
                        :customers="customers"
                        :suppliers="suppliers"
                        :items="items"
                        submit-label="Criar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
