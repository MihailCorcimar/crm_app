<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { ref } from 'vue';

type CountryOption = {
    id: number;
    code: string;
    name: string;
};

const props = defineProps<{
    form: {
        type: string;
        tax_id: string;
        name: string;
        phone: string;
        mobile: string;
        website: string;
        email: string;
        status: string;
        address: string;
        postal_code: string;
        city: string;
        country_id: number | '';
        notes: string;
        gdpr_consent: boolean;
        errors: Record<string, string>;
        processing: boolean;
    };
    countries: CountryOption[];
    submitLabel: string;
}>();

const viesLoading = ref(false);
const viesMessage = ref('');
const viesMessageType = ref<'error' | 'success' | ''>('');

const emit = defineEmits<{
    submit: [];
}>();

function normalizeTaxId(value: string): string {
    return value.replace(/\D+/g, '').slice(0, 9);
}

async function lookupVies(): Promise<void> {
    const taxId = normalizeTaxId(props.form.tax_id);
    props.form.tax_id = taxId;

    if (taxId.length !== 9) {
        viesMessage.value = '';
        viesMessageType.value = '';
        return;
    }

    viesLoading.value = true;
    viesMessage.value = '';
    viesMessageType.value = '';

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    try {
        const response = await fetch('/entities/vies', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
            },
            body: JSON.stringify({
                tax_id: taxId,
                country_code: 'PT',
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            viesMessage.value = payload.message ?? 'Nao foi possivel consultar o VIES.';
            viesMessageType.value = 'error';
            return;
        }

        if (!payload.valid) {
            viesMessage.value = 'NIF nao valido no VIES.';
            viesMessageType.value = 'error';
            return;
        }

        if (payload.name && !props.form.name) {
            props.form.name = payload.name;
        }
        if (payload.address && !props.form.address) {
            props.form.address = payload.address;
        }
        if (payload.postal_code && !props.form.postal_code) {
            props.form.postal_code = payload.postal_code;
        }
        if (payload.city && !props.form.city) {
            props.form.city = payload.city;
        }
        if (payload.country_code) {
            const matchedCountry = props.countries.find(
                (country) => country.code.toUpperCase() === String(payload.country_code).toUpperCase(),
            );

            if (matchedCountry) {
                props.form.country_id = matchedCountry.id;
            }
        }

        viesMessage.value = 'Dados preenchidos com sucesso via VIES.';
        viesMessageType.value = 'success';
    } catch {
        viesMessage.value = 'Erro na consulta ao VIES.';
        viesMessageType.value = 'error';
    } finally {
        viesLoading.value = false;
    }
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="type">
                <FormItem>
                    <FormLabel>Tipo</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.type"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            required
                        >
                            <option value="customer">Cliente</option>
                            <option value="supplier">Fornecedor</option>
                            <option value="both">Cliente e Fornecedor</option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.type }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="status">
                <FormItem>
                    <FormLabel>Estado</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.status"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            required
                        >
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.status }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="tax_id">
                <FormItem>
                    <FormLabel>NIF</FormLabel>
                    <FormControl>
                        <div class="flex gap-2">
                            <Input
                                v-model="form.tax_id"
                                inputmode="numeric"
                                maxlength="9"
                                placeholder="123456789"
                                required
                                @blur="lookupVies"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="viesLoading"
                                @click="lookupVies"
                            >
                                {{ viesLoading ? 'A consultar...' : 'VIES' }}
                            </Button>
                        </div>
                    </FormControl>
                    <FormDescription
                        v-if="viesMessage"
                        :class="viesMessageType === 'error' ? 'text-destructive' : 'text-emerald-600'"
                    >
                        {{ viesMessage }}
                    </FormDescription>
                    <FormMessage>{{ form.errors.tax_id }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="name">
                <FormItem>
                    <FormLabel>Nome</FormLabel>
                    <FormControl>
                        <Input v-model="form.name" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.name }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="phone">
                <FormItem>
                    <FormLabel>Telefone</FormLabel>
                    <FormControl>
                        <Input
                            v-model="form.phone"
                            inputmode="tel"
                            maxlength="14"
                            pattern="^(\+351 ?)?2[0-9]{8}$"
                            placeholder="212345678 ou +351 212345678"
                        />
                    </FormControl>
                    <FormDescription>Formato PT: telefone fixo (2 + 8 digitos, opcional +351).</FormDescription>
                    <FormMessage>{{ form.errors.phone }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="mobile">
                <FormItem>
                    <FormLabel>Telemovel</FormLabel>
                    <FormControl>
                        <Input
                            v-model="form.mobile"
                            inputmode="tel"
                            maxlength="14"
                            pattern="^(\+351 ?)?9[0-9]{8}$"
                            placeholder="912345678 ou +351 912345678"
                        />
                    </FormControl>
                    <FormDescription>Formato PT: numero movel (9 digitos, opcional +351).</FormDescription>
                    <FormMessage>{{ form.errors.mobile }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="website">
                <FormItem>
                    <FormLabel>Website</FormLabel>
                    <FormControl>
                        <Input v-model="form.website" />
                    </FormControl>
                    <FormMessage>{{ form.errors.website }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="email">
                <FormItem>
                    <FormLabel>Email</FormLabel>
                    <FormControl>
                        <Input v-model="form.email" type="email" />
                    </FormControl>
                    <FormMessage>{{ form.errors.email }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="country_id">
                <FormItem>
                    <FormLabel>Pais</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.country_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            required
                        >
                            <option :value="''" disabled>Selecionar pais</option>
                            <option
                                v-for="country in countries"
                                :key="country.id"
                                :value="country.id"
                            >
                                {{ country.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.country_id }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="city">
                <FormItem>
                    <FormLabel>Localidade</FormLabel>
                    <FormControl>
                        <Input v-model="form.city" />
                    </FormControl>
                    <FormMessage>{{ form.errors.city }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="address">
                <FormItem>
                    <FormLabel>Morada</FormLabel>
                    <FormControl>
                        <Input v-model="form.address" />
                    </FormControl>
                    <FormMessage>{{ form.errors.address }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="postal_code">
                <FormItem>
                    <FormLabel>Codigo Postal</FormLabel>
                    <FormControl>
                        <Input v-model="form.postal_code" placeholder="0000-000" />
                    </FormControl>
                    <FormDescription>Formato: XXXX-XXX</FormDescription>
                    <FormMessage>{{ form.errors.postal_code }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <FormField name="notes">
            <FormItem>
                <FormLabel>Observacoes</FormLabel>
                <FormControl>
                    <textarea
                        v-model="form.notes"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-24 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </FormControl>
                <FormMessage>{{ form.errors.notes }}</FormMessage>
            </FormItem>
        </FormField>

        <FormField name="gdpr_consent">
            <FormItem class="space-y-0">
                <FormControl>
                    <label class="flex items-center gap-3">
                        <Checkbox v-model:checked="form.gdpr_consent" />
                        <span>Consentimento RGPD (Sim/Nao)</span>
                    </label>
                </FormControl>
                <FormMessage>{{ form.errors.gdpr_consent }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/entities">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
