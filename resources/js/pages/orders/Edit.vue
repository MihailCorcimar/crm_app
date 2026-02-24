<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import OrderForm from '@/components/orders/OrderForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type CustomerOption = { id: number; name: string };
type SupplierOption = { id: number; name: string };
type ItemOption = { id: number; reference: string; name: string; description: string | null; price: number };
type OrderPayload = {
    id: number;
    number: number;
    order_date: string | null;
    valid_until: string | null;
    customer_id: number;
    status: 'draft' | 'closed';
    lines: Array<{ item_id: number; supplier_id: number | null; quantity: number; sale_price: number; cost_price: number }>;
};

const props = defineProps<{
    order: OrderPayload;
    customers: CustomerOption[];
    suppliers: SupplierOption[];
    items: ItemOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Encomendas', href: '/orders' },
    { title: `Editar encomenda #${props.order.number}`, href: `/orders/${props.order.id}/edit` },
];

const form = useForm({
    number: props.order.number,
    order_date: props.order.order_date ?? '',
    valid_until: props.order.valid_until ?? '',
    customer_id: props.order.customer_id,
    status: props.order.status,
    lines: props.order.lines.map((line) => ({
        item_id: line.item_id,
        supplier_id: line.supplier_id ?? '',
        quantity: line.quantity,
        sale_price: line.sale_price,
        cost_price: line.cost_price,
        search: '',
    })),
});

function submit(): void {
    form.put(`/orders/${props.order.id}`);
}
</script>

<template>
    <Head title="Editar encomenda" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader><CardTitle>Editar encomenda</CardTitle></CardHeader>
                <CardContent>
                    <OrderForm
                        :form="form"
                        :customers="customers"
                        :suppliers="suppliers"
                        :items="items"
                        submit-label="Guardar"
                        @submit="submit"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
