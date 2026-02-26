<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type SelectOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    form: {
        number: number | '';
        entity_id: number | '';
        first_name: string;
        last_name: string;
        role_id: number | '';
        phone: string;
        mobile: string;
        email: string;
        gdpr_consent: boolean;
        notes: string;
        status: string;
        errors: Record<string, string>;
        processing: boolean;
    };
    entities: SelectOption[];
    roles: SelectOption[];
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

const numberDisplay = computed(() =>
    props.form.number === '' ? 'Automatico (na criacao)' : String(props.form.number),
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="number">
                <FormItem>
                    <FormLabel>Numero</FormLabel>
                    <FormControl>
                        <Input :model-value="numberDisplay" disabled />
                    </FormControl>
                    <FormDescription>Gerado automaticamente de forma incremental.</FormDescription>
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
            <FormField name="entity_id">
                <FormItem>
                    <FormLabel>Entidade</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.entity_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Sem entidade associada</option>
                            <option v-for="entity in entities" :key="entity.id" :value="entity.id">
                                {{ entity.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.entity_id }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="role_id">
                <FormItem>
                    <FormLabel>Funcao</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.role_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            required
                        >
                            <option :value="''" disabled>Selecionar funcao</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">
                                {{ role.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.role_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="first_name">
                <FormItem>
                    <FormLabel>Nome</FormLabel>
                    <FormControl>
                        <Input v-model="form.first_name" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.first_name }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="last_name">
                <FormItem>
                    <FormLabel>Apelido</FormLabel>
                    <FormControl>
                        <Input v-model="form.last_name" />
                    </FormControl>
                    <FormMessage>{{ form.errors.last_name }}</FormMessage>
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

        <FormField name="email">
            <FormItem>
                <FormLabel>Email</FormLabel>
                <FormControl>
                    <Input v-model="form.email" type="email" />
                </FormControl>
                <FormMessage>{{ form.errors.email }}</FormMessage>
            </FormItem>
        </FormField>

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
                <Link href="/people">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
