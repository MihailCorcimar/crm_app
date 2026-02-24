<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type SupplierOption = { id: number; name: string };
type SupplierOrderOption = {
    id: number;
    number: number;
    supplier_id: number;
    supplier: string;
    total: number;
    order_date: string | null;
    status: string;
};

const props = defineProps<{
    form: {
        number?: number | '';
        invoice_date: string;
        due_date: string;
        supplier_id: number | string | '';
        supplier_order_id: number | string | '';
        total: number | string;
        document: File | null;
        payment_proof: File | null;
        status: 'pending_payment' | 'paid';
        send_payment_proof_email: boolean;
        errors: Record<string, string>;
        processing: boolean;
    };
    suppliers: SupplierOption[];
    supplierOrders: SupplierOrderOption[];
    submitLabel: string;
    existingDocumentUrl?: string | null;
    existingPaymentProofUrl?: string | null;
}>();

const emit = defineEmits<{
    submit: [];
}>();

const documentName = ref('');
const paymentProofName = ref('');

const filteredSupplierOrders = computed(() => {
    const supplierId = Number(props.form.supplier_id);
    if (!supplierId) {
        return props.supplierOrders;
    }

    return props.supplierOrders.filter((order) => order.supplier_id === supplierId);
});

watch(
    () => props.form.status,
    (newStatus, oldStatus) => {
        if (newStatus !== 'paid') {
            props.form.send_payment_proof_email = false;
            return;
        }

        if (oldStatus === 'paid') {
            return;
        }

        const confirmed = window.confirm('Pretende enviar o comprovativo ao Fornecedor?');
        props.form.send_payment_proof_email = confirmed;
    },
);

function onSupplierOrderChange(): void {
    const supplierOrderId = Number(props.form.supplier_order_id);
    if (!supplierOrderId) {
        return;
    }

    const selectedOrder = props.supplierOrders.find((order) => order.id === supplierOrderId);
    if (!selectedOrder) {
        return;
    }

    props.form.total = selectedOrder.total;
    if (!props.form.supplier_id) {
        props.form.supplier_id = selectedOrder.supplier_id;
    }
}

function onDocumentChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    props.form.document = file;
    documentName.value = file?.name ?? '';
}

function onPaymentProofChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    props.form.payment_proof = file;
    paymentProofName.value = file?.name ?? '';
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="number">
                <FormItem>
                    <FormLabel>Numero</FormLabel>
                    <FormControl>
                        <Input :model-value="form.number ? String(form.number) : 'Automatico'" disabled />
                    </FormControl>
                </FormItem>
            </FormField>

            <FormField name="status">
                <FormItem>
                    <FormLabel>Estado</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.status"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option value="pending_payment">Pendente de Pagamento</option>
                            <option value="paid">Paga</option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.status }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <FormField name="invoice_date">
                <FormItem>
                    <FormLabel>Data da Fatura</FormLabel>
                    <FormControl>
                        <Input v-model="form.invoice_date" type="date" />
                    </FormControl>
                    <FormMessage>{{ form.errors.invoice_date }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="due_date">
                <FormItem>
                    <FormLabel>Data de Vencimento</FormLabel>
                    <FormControl>
                        <Input v-model="form.due_date" type="date" />
                    </FormControl>
                    <FormMessage>{{ form.errors.due_date }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="total">
                <FormItem>
                    <FormLabel>Valor Total</FormLabel>
                    <FormControl>
                        <Input v-model.number="form.total" type="number" min="0" step="0.01" />
                    </FormControl>
                    <FormMessage>{{ form.errors.total }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="supplier_id">
                <FormItem>
                    <FormLabel>Fornecedor</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.supplier_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option :value="''" disabled>Selecionar fornecedor</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.supplier_id }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="supplier_order_id">
                <FormItem>
                    <FormLabel>Encomenda Fornecedor</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.supplier_order_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            @change="onSupplierOrderChange"
                        >
                            <option :value="''">Sem encomenda</option>
                            <option v-for="supplierOrder in filteredSupplierOrders" :key="supplierOrder.id" :value="supplierOrder.id">
                                #{{ supplierOrder.number }} - {{ supplierOrder.supplier }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.supplier_order_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="document">
                <FormItem>
                    <FormLabel>Documento</FormLabel>
                    <FormControl>
                        <Input type="file" @change="onDocumentChange" />
                    </FormControl>
                    <FormDescription v-if="documentName">Selecionado: {{ documentName }}</FormDescription>
                    <FormDescription v-else-if="existingDocumentUrl">
                        Documento atual: <Link :href="existingDocumentUrl" class="underline">abrir</Link>
                    </FormDescription>
                    <FormMessage>{{ form.errors.document }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="payment_proof">
                <FormItem>
                    <FormLabel>Comprovativo de Pagamento</FormLabel>
                    <FormControl>
                        <Input type="file" @change="onPaymentProofChange" />
                    </FormControl>
                    <FormDescription v-if="paymentProofName">Selecionado: {{ paymentProofName }}</FormDescription>
                    <FormDescription v-else-if="existingPaymentProofUrl">
                        Comprovativo atual: <Link :href="existingPaymentProofUrl" class="underline">abrir</Link>
                    </FormDescription>
                    <FormMessage>{{ form.errors.payment_proof }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <FormField v-if="form.status === 'paid'" name="send_payment_proof_email">
            <FormItem>
                <FormLabel>Envio para o fornecedor</FormLabel>
                <FormControl>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input v-model="form.send_payment_proof_email" type="checkbox" class="h-4 w-4" />
                        Enviar comprovativo por email ao fornecedor
                    </label>
                </FormControl>
                <FormDescription>Assunto: Comprovativo de Pagamento - Fatura "Numero".</FormDescription>
            </FormItem>
        </FormField>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
