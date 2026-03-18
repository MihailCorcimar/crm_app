<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type FieldType = 'text' | 'email' | 'tel' | 'textarea' | 'number' | 'date' | 'select' | 'checkbox';

type FieldSchemaRow = {
    key: string;
    label: string;
    type: FieldType;
    enabled: boolean;
    required: boolean;
    is_system?: boolean;
    options?: string[];
};

type ConversionSettings = {
    create_deal: boolean;
    entity_name_field_key: string | null;
    deal_title_field_key: string | null;
    deal_title_template: string;
    deal_value_field_key: string | null;
    deal_stage: string;
    deal_owner_id: number | null;
    deal_probability: number;
};

type FieldTypeOption = {
    value: FieldType;
    label: string;
};

type TemplateVariableOption = {
    token: string;
    label: string;
};

const FIELD_TYPE_OPTIONS: FieldTypeOption[] = [
    { value: 'text', label: 'Texto curto' },
    { value: 'textarea', label: 'Texto longo' },
    { value: 'email', label: 'Email' },
    { value: 'tel', label: 'Telefone' },
    { value: 'number', label: 'Numero' },
    { value: 'date', label: 'Data' },
    { value: 'select', label: 'Lista (select)' },
    { value: 'checkbox', label: 'Caixa de selecao' },
];

const TITLE_TEMPLATE_VARIABLES: TemplateVariableOption[] = [
    { token: '{entity_name}', label: 'Nome da entidade' },
    { token: '{full_name}', label: 'Nome completo' },
    { token: '{source_type}', label: 'Origem' },
];

const props = defineProps<{
    form: {
        name: string;
        slug: string;
        status: 'active' | 'inactive';
        requires_captcha: boolean;
        confirmation_message: string;
        field_schema: FieldSchemaRow[];
        conversion_settings: ConversionSettings;
        errors: Record<string, string>;
        processing: boolean;
    };
    ownerOptions: Array<{ id: number; name: string }>;
    stageOptions: Array<{ value: string; label: string }>;
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

function onNameChanged(): void {
    if (props.form.slug.trim() !== '') {
        return;
    }

    props.form.slug = props.form.name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 140);
}

function nextCustomKey(): string {
    let index = 1;
    let key = `custom_field_${index}`;
    const used = new Set(props.form.field_schema.map((field) => field.key));

    while (used.has(key)) {
        index += 1;
        key = `custom_field_${index}`;
    }

    return key;
}

function ensureFieldOptions(index: number): void {
    if (props.form.field_schema[index].type !== 'select') {
        props.form.field_schema[index].options = [];
        return;
    }

    if (!Array.isArray(props.form.field_schema[index].options)) {
        props.form.field_schema[index].options = ['Opcao 1'];
    }

    if ((props.form.field_schema[index].options ?? []).length === 0) {
        props.form.field_schema[index].options = ['Opcao 1'];
    }
}

function addCustomField(): void {
    props.form.field_schema.push({
        key: nextCustomKey(),
        label: 'Novo campo',
        type: 'text',
        enabled: true,
        required: false,
        is_system: false,
        options: [],
    });
}

function removeCustomField(index: number): void {
    if (props.form.field_schema[index].is_system) {
        return;
    }

    props.form.field_schema.splice(index, 1);
}

function optionsAsText(field: FieldSchemaRow): string {
    if (field.type !== 'select') {
        return '';
    }

    return (field.options ?? []).join('\n');
}

function updateFieldOptions(index: number, raw: string): void {
    const items = raw
        .split(/\r?\n|,/g)
        .map((item) => item.trim())
        .filter((item, idx, arr) => item !== '' && arr.indexOf(item) === idx)
        .slice(0, 40);

    props.form.field_schema[index].options = items;
}

const fieldKeyOptions = computed(() => {
    const occurrences = new Map<string, number>();

    props.form.field_schema.forEach((field) => {
        const base = (field.label ?? '').trim() || 'Campo sem nome';
        occurrences.set(base, (occurrences.get(base) ?? 0) + 1);
    });

    const running = new Map<string, number>();

    return props.form.field_schema.map((field) => {
        const base = (field.label ?? '').trim() || 'Campo sem nome';
        const total = occurrences.get(base) ?? 1;
        const current = (running.get(base) ?? 0) + 1;
        running.set(base, current);

        return {
            key: field.key,
            label: total > 1 ? `${base} ${current}` : base,
        };
    });
});

function addTemplateVariable(token: string): void {
    const current = (props.form.conversion_settings.deal_title_template ?? '').trim();
    if (current === '') {
        props.form.conversion_settings.deal_title_template = token;

        return;
    }

    props.form.conversion_settings.deal_title_template = `${current} ${token}`;
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1">
                <label class="text-sm font-medium">Nome do formulario</label>
                <Input v-model="form.name" required maxlength="120" @input="onNameChanged" />
                <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div class="grid gap-1">
                <label class="text-sm font-medium">Slug</label>
                <Input v-model="form.slug" required maxlength="140" placeholder="formulario-leads-site" />
                <p class="text-xs text-muted-foreground">Usado para identificar o formulario internamente.</p>
                <p v-if="form.errors.slug" class="text-sm text-red-600">{{ form.errors.slug }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1">
                <label class="text-sm font-medium">Estado</label>
                <select
                    v-model="form.status"
                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                    required
                >
                    <option value="active">Ativo</option>
                    <option value="inactive">Inativo</option>
                </select>
                <p v-if="form.errors.status" class="text-sm text-red-600">{{ form.errors.status }}</p>
            </div>

            <label class="mt-6 inline-flex items-center gap-2 text-sm font-medium">
                <input v-model="form.requires_captcha" type="checkbox">
                Captcha obrigatorio
            </label>
        </div>

        <div class="grid gap-1">
            <label class="text-sm font-medium">Mensagem de confirmacao</label>
            <textarea
                v-model="form.confirmation_message"
                rows="3"
                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                required
                maxlength="1000"
            />
            <p v-if="form.errors.confirmation_message" class="text-sm text-red-600">{{ form.errors.confirmation_message }}</p>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Campos configuraveis</h3>
                <Button type="button" variant="outline" size="sm" @click="addCustomField">Adicionar campo</Button>
            </div>

            <div class="overflow-x-auto rounded-md border">
                <table class="min-w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Etiqueta</th>
                            <th class="px-3 py-2 text-left font-medium">Tipo</th>
                            <th class="px-3 py-2 text-center font-medium">Ativo</th>
                            <th class="px-3 py-2 text-center font-medium">Obrigatorio</th>
                            <th class="px-3 py-2 text-left font-medium">Opcoes</th>
                            <th class="px-3 py-2 text-right font-medium">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(field, index) in form.field_schema" :key="`${field.key}-${index}`" class="border-t align-top">
                            <td class="px-3 py-2">
                                <Input v-model="form.field_schema[index].label" maxlength="60" required />
                            </td>
                            <td class="px-3 py-2">
                                <select
                                    v-model="form.field_schema[index].type"
                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                    :disabled="field.is_system"
                                    @change="ensureFieldOptions(index)"
                                >
                                    <option v-for="typeOption in FIELD_TYPE_OPTIONS" :key="typeOption.value" :value="typeOption.value">
                                        {{ typeOption.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input v-model="form.field_schema[index].enabled" type="checkbox">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <input
                                    v-model="form.field_schema[index].required"
                                    type="checkbox"
                                    :disabled="!form.field_schema[index].enabled"
                                >
                            </td>
                            <td class="px-3 py-2">
                                <textarea
                                    v-if="field.type === 'select'"
                                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-20 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                                    :value="optionsAsText(field)"
                                    placeholder="Uma opcao por linha"
                                    @input="updateFieldOptions(index, ($event.target as HTMLTextAreaElement).value)"
                                />
                                <span v-else class="text-xs text-muted-foreground">-</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <Button
                                    v-if="!field.is_system"
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    @click="removeCustomField(index)"
                                >
                                    Remover
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="form.errors.field_schema" class="text-sm text-red-600">{{ form.errors.field_schema }}</p>
        </div>

        <div class="space-y-3 rounded-md border p-4">
            <h3 class="text-sm font-semibold">Conversao de lead</h3>
            <p class="text-xs text-muted-foreground">
                Define como cada submissao pode ser convertida em entidade e negocio.
            </p>

            <label class="inline-flex items-center gap-2 text-sm font-medium">
                <input v-model="form.conversion_settings.create_deal" type="checkbox">
                Criar negocio automaticamente na conversao
            </label>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-1">
                    <label class="text-sm font-medium">Campo para nome da entidade</label>
                    <select
                        v-model="form.conversion_settings.entity_name_field_key"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                    >
                        <option :value="null">Sem mapeamento</option>
                        <option v-for="option in fieldKeyOptions" :key="`entity-${option.key}`" :value="option.key">
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1">
                    <label class="text-sm font-medium">Campo para valor do negocio</label>
                    <select
                        v-model="form.conversion_settings.deal_value_field_key"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        :disabled="!form.conversion_settings.create_deal"
                    >
                        <option :value="null">Sem mapeamento</option>
                        <option v-for="option in fieldKeyOptions" :key="`value-${option.key}`" :value="option.key">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-1">
                    <label class="text-sm font-medium">Campo para titulo do negocio</label>
                    <select
                        v-model="form.conversion_settings.deal_title_field_key"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        :disabled="!form.conversion_settings.create_deal"
                    >
                        <option :value="null">Usar template</option>
                        <option v-for="option in fieldKeyOptions" :key="`title-${option.key}`" :value="option.key">
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1">
                    <label class="text-sm font-medium">Template do titulo</label>
                    <Input
                        v-model="form.conversion_settings.deal_title_template"
                        maxlength="180"
                        :disabled="!form.conversion_settings.create_deal"
                        placeholder="Ex.: Lead inbound - Nome da entidade"
                    />
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="item in TITLE_TEMPLATE_VARIABLES"
                            :key="item.token"
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="!form.conversion_settings.create_deal"
                            @click="addTemplateVariable(item.token)"
                        >
                            {{ item.label }}
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">Use os botoes para inserir atalhos dinamicos no titulo.</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-1">
                    <label class="text-sm font-medium">Etapa inicial</label>
                    <select
                        v-model="form.conversion_settings.deal_stage"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        :disabled="!form.conversion_settings.create_deal"
                    >
                        <option v-for="stage in stageOptions" :key="stage.value" :value="stage.value">
                            {{ stage.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1">
                    <label class="text-sm font-medium">Responsavel do negocio</label>
                    <select
                        v-model="form.conversion_settings.deal_owner_id"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        :disabled="!form.conversion_settings.create_deal"
                    >
                        <option :value="null">Utilizador que converte</option>
                        <option v-for="owner in ownerOptions" :key="owner.id" :value="owner.id">
                            {{ owner.name }}
                        </option>
                    </select>
                    <p v-if="form.errors['conversion_settings.deal_owner_id']" class="text-sm text-red-600">
                        {{ form.errors['conversion_settings.deal_owner_id'] }}
                    </p>
                </div>

                <div class="grid gap-1">
                    <label class="text-sm font-medium">Probabilidade inicial (%)</label>
                    <Input
                        v-model="form.conversion_settings.deal_probability"
                        type="number"
                        min="0"
                        max="100"
                        :disabled="!form.conversion_settings.create_deal"
                    />
                </div>
            </div>

            <p v-if="form.errors.conversion_settings" class="text-sm text-red-600">{{ form.errors.conversion_settings }}</p>
        </div>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel }}
            </Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/lead-forms">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
