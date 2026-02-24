<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ProposalForm from '@/components/proposals/ProposalForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

type CustomerOption = { id: number; name: string };
type SupplierOption = { id: number; name: string };
type ItemOption = { id: number; reference: string; name: string; description: string | null; price: number };
type ProposalPayload = {
    id: number;
    number: number;
    proposal_date: string | null;
    valid_until: string;
    customer_id: number;
    status: 'draft' | 'closed';
    lines: Array<{ item_id: number; supplier_id: number | null; quantity: number; sale_price: number; cost_price: number }>;
};

const props = defineProps<{
    proposal: ProposalPayload;
    customers: CustomerOption[];
    suppliers: SupplierOption[];
    items: ItemOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Propostas', href: '/proposals' },
    { title: `Editar proposta #${props.proposal.number}`, href: `/proposals/${props.proposal.id}/edit` },
];

const form = useForm({
    number: props.proposal.number,
    proposal_date: props.proposal.proposal_date ?? '',
    valid_until: props.proposal.valid_until,
    customer_id: props.proposal.customer_id,
    status: props.proposal.status,
    lines: props.proposal.lines.map((line) => ({
        item_id: line.item_id,
        supplier_id: line.supplier_id ?? '',
        quantity: line.quantity,
        sale_price: line.sale_price,
        cost_price: line.cost_price,
        search: '',
    })),
});

function submit(): void {
    form.put(`/proposals/${props.proposal.id}`);
}
</script>

<template>
    <Head title="Editar proposta" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader><CardTitle>Editar proposta</CardTitle></CardHeader>
                <CardContent>
                    <ProposalForm
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
