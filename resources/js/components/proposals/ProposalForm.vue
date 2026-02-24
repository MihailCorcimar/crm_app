<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type CustomerOption = { id: number; name: string };
type SupplierOption = { id: number; name: string };
type ItemOption = { id: number; reference: string | null; name: string | null; description: string | null; price: number };
type ProposalLine = {
    item_id: number | string | '';
    supplier_id: number | string | '';
    quantity: number;
    sale_price: number;
    cost_price: number;
    search: string;
};

const props = defineProps<{
    form: {
        number?: number | '';
        proposal_date: string;
        valid_until: string;
        customer_id: number | string | '';
        status: 'draft' | 'closed';
        lines: ProposalLine[];
        errors: Record<string, string>;
        processing: boolean;
    };
    customers: CustomerOption[];
    suppliers: SupplierOption[];
    items: ItemOption[];
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

function addLine(): void {
    props.form.lines.push({
        item_id: '',
        supplier_id: '',
        quantity: 1,
        sale_price: 0,
        cost_price: 0,
        search: '',
    });
}

function removeLine(index: number): void {
    props.form.lines.splice(index, 1);
}

function itemLabel(item: ItemOption): string {
    return `${item.reference ?? '-'} - ${item.name ?? '-'}`;
}

function lineItems(index: number): ItemOption[] {
    const query = props.form.lines[index]?.search?.toLowerCase().trim();
    if (!query) {
        return props.items;
    }

    return props.items.filter((item) =>
        `${item.reference ?? ''} ${item.name ?? ''}`.toLowerCase().includes(query),
    );
}

function onItemChange(index: number): void {
    const selectedId = Number(props.form.lines[index].item_id);
    const selected = props.items.find((item) => item.id === selectedId);
    if (!selected) {
        return;
    }

    props.form.lines[index].sale_price = selected.price;
    props.form.lines[index].search = `${selected.reference ?? ''} ${selected.name ?? ''}`.trim();
}

const total = computed(() =>
    props.form.lines.reduce((acc, line) => acc + (Number(line.quantity) * Number(line.sale_price)), 0),
);
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
                            <option value="draft">Rascunho</option>
                            <option value="closed">Fechado</option>
                        </select>
                    </FormControl>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <FormField name="proposal_date">
                <FormItem>
                    <FormLabel>Data da Proposta</FormLabel>
                    <FormControl>
                        <Input v-model="form.proposal_date" type="date" />
                    </FormControl>
                    <FormDescription>Data em que a proposta fica fechada.</FormDescription>
                    <FormMessage>{{ form.errors.proposal_date }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="valid_until">
                <FormItem>
                    <FormLabel>Validade</FormLabel>
                    <FormControl>
                        <Input v-model="form.valid_until" type="date" />
                    </FormControl>
                    <FormMessage>{{ form.errors.valid_until }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="customer_id">
                <FormItem>
                    <FormLabel>Cliente</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.customer_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option :value="''" disabled>Selecionar cliente</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.customer_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="space-y-3 rounded-md border p-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium">Linhas dos Artigos</h3>
                <Button type="button" size="sm" variant="outline" @click="addLine">Adicionar linha</Button>
            </div>

            <div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 rounded-md border p-3 md:grid-cols-6">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium">Pesquisar artigo</label>
                    <Input v-model="line.search" placeholder="Referencia ou nome" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium">Artigo</label>
                    <select
                        v-model="line.item_id"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                        @change="onItemChange(index)"
                    >
                        <option :value="''" disabled>Selecionar artigo</option>
                        <option v-for="item in lineItems(index)" :key="item.id" :value="item.id">
                            {{ itemLabel(item) }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Fornecedor</label>
                    <select
                        v-model="line.supplier_id"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                    >
                        <option :value="''">-</option>
                        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                            {{ supplier.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Qtd</label>
                    <Input v-model.number="line.quantity" type="number" min="1" step="1" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Preco</label>
                    <Input v-model.number="line.sale_price" type="number" min="0" step="0.01" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Preco custo</label>
                    <Input v-model.number="line.cost_price" type="number" min="0" step="0.01" />
                </div>
                <div class="flex items-end">
                    <Button type="button" size="sm" variant="destructive" @click="removeLine(index)">
                        Remover
                    </Button>
                </div>
            </div>
            <p class="text-sm font-medium">Valor total: {{ total.toFixed(2) }} €</p>
            <p v-if="form.errors.lines" class="text-destructive text-sm font-medium">
                {{ form.errors.lines }}
            </p>
        </div>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
