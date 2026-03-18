<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type RuleForm = {
    name: string;
    inactivity_days: number | string;
    activity_type: 'call' | 'task' | 'meeting' | 'note';
    activity_due_in_days: number | string;
    activity_priority: 'low' | 'medium' | 'high';
    activity_title_template: string;
    activity_description_template: string;
    notify_internal: boolean;
    notification_message: string;
    status: 'active' | 'paused';
    errors: Record<string, string>;
    processing: boolean;
};

type RulePreset = {
    id: string;
    label: string;
    description: string;
    payload: {
        activity_type: RuleForm['activity_type'];
        activity_priority: RuleForm['activity_priority'];
        activity_due_in_days: number;
        activity_title_template: string;
        activity_description_template: string;
        notification_message: string;
    };
};

const props = defineProps<{
    form: RuleForm;
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

const placeholderTokens = [
    '{deal_title}',
    '{entity_name}',
    '{owner_name}',
    '{days_without_activity}',
] as const;

const placeholderLabels: Record<(typeof placeholderTokens)[number], string> = {
    '{deal_title}': 'Título do negócio',
    '{entity_name}': 'Entidade',
    '{owner_name}': 'Responsável',
    '{days_without_activity}': 'Dias sem atividade',
};

const placeholderDisplayTags: Record<(typeof placeholderTokens)[number], string> = {
    '{deal_title}': '[Título do negócio]',
    '{entity_name}': '[Entidade]',
    '{owner_name}': '[Responsável]',
    '{days_without_activity}': '[Dias sem atividade]',
};

const previewValues: Record<(typeof placeholderTokens)[number], string> = {
    '{deal_title}': 'Venda de Legumes - Intermerceana',
    '{entity_name}': 'Intermerceana Supermercados Lda',
    '{owner_name}': 'Mihail',
    '{days_without_activity}': '7',
};

const presets: RulePreset[] = [
    {
        id: 'follow_up_rapido',
        label: 'Follow-up rápido',
        description: 'Contacto inicial após inatividade curta.',
        payload: {
            activity_type: 'call',
            activity_priority: 'high',
            activity_due_in_days: 1,
            activity_title_template: 'Follow up automático - {deal_title}',
            activity_description_template: 'Negócio sem atividade há {days_without_activity} dias. Retomar contacto com {entity_name}.',
            notification_message: 'Foi criada uma nova atividade automática para {deal_title}.',
        },
    },
    {
        id: 'reativacao_pipeline',
        label: 'Reativação de pipeline',
        description: 'Reativar negócios sem atualização recente.',
        payload: {
            activity_type: 'task',
            activity_priority: 'medium',
            activity_due_in_days: 2,
            activity_title_template: 'Reativar negociação - {deal_title}',
            activity_description_template: 'Negócio parado há {days_without_activity} dias. Rever estado com {owner_name}.',
            notification_message: 'Regra executada: criar tarefa para {deal_title}.',
        },
    },
    {
        id: 'pedido_atualizacao',
        label: 'Pedido de atualização',
        description: 'Agendar reunião para desbloquear decisão.',
        payload: {
            activity_type: 'meeting',
            activity_priority: 'high',
            activity_due_in_days: 3,
            activity_title_template: 'Marcar reunião - {entity_name}',
            activity_description_template: 'Sem resposta no negócio {deal_title} há {days_without_activity} dias. Agendar reunião de alinhamento.',
            notification_message: 'Foi proposta reunião para o negócio {deal_title}.',
        },
    },
];

const selectedPresetId = ref<string>(presets[0]?.id ?? '');
const copiedPreset = ref<boolean>(false);

const titleInputRef = ref<HTMLInputElement | null>(null);
const descriptionTextareaRef = ref<HTMLTextAreaElement | null>(null);
const notificationInputRef = ref<HTMLInputElement | null>(null);

type TemplateField = 'activity_title_template' | 'activity_description_template' | 'notification_message';

const titleTemplateDisplay = ref<string>('');
const descriptionTemplateDisplay = ref<string>('');
const notificationTemplateDisplay = ref<string>('');

const selectedPreset = computed<RulePreset | null>(() => {
    return presets.find((preset) => preset.id === selectedPresetId.value) ?? null;
});

function previewTemplate(value: string): string {
    let rendered = value ?? '';

    for (const token of placeholderTokens) {
        rendered = rendered.replaceAll(token, previewValues[token]);
    }

    return rendered.trim() === '' ? '-' : rendered;
}

function templateToDisplay(value: string): string {
    let rendered = value ?? '';

    for (const token of placeholderTokens) {
        rendered = rendered.replaceAll(token, placeholderDisplayTags[token]);
    }

    return rendered;
}

function displayToTemplate(value: string): string {
    let rendered = value ?? '';

    for (const token of placeholderTokens) {
        rendered = rendered.replaceAll(placeholderDisplayTags[token], token);
    }

    return rendered;
}

function syncDisplayFromForm(): void {
    titleTemplateDisplay.value = templateToDisplay(props.form.activity_title_template ?? '');
    descriptionTemplateDisplay.value = templateToDisplay(props.form.activity_description_template ?? '');
    notificationTemplateDisplay.value = templateToDisplay(props.form.notification_message ?? '');
}

watch(
    [titleTemplateDisplay, descriptionTemplateDisplay, notificationTemplateDisplay],
    () => {
        props.form.activity_title_template = displayToTemplate(titleTemplateDisplay.value);
        props.form.activity_description_template = displayToTemplate(descriptionTemplateDisplay.value);
        props.form.notification_message = displayToTemplate(notificationTemplateDisplay.value);
    },
    { immediate: true },
);

watch(
    () => [props.form.activity_title_template, props.form.activity_description_template, props.form.notification_message],
    () => {
        syncDisplayFromForm();
    },
    { immediate: true },
);

function appendWithSpace(base: string, token: string): string {
    if (base.trim() === '') {
        return token;
    }

    if (base.endsWith(' ') || base.endsWith('\n')) {
        return `${base}${token}`;
    }

    return `${base} ${token}`;
}

function insertToken(field: TemplateField, token: string): void {
    const displayToken = placeholderDisplayTags[token];

    const targetDisplayRef = field === 'activity_title_template'
        ? titleTemplateDisplay
        : field === 'activity_description_template'
            ? descriptionTemplateDisplay
            : notificationTemplateDisplay;

    const currentValue = String(targetDisplayRef.value ?? '');

    const target = field === 'activity_title_template'
        ? titleInputRef.value
        : field === 'activity_description_template'
            ? descriptionTextareaRef.value
            : notificationInputRef.value;

    if (!target || target.selectionStart === null || target.selectionEnd === null) {
        targetDisplayRef.value = appendWithSpace(currentValue, displayToken);
        return;
    }

    const start = target.selectionStart;
    const end = target.selectionEnd;
    const nextValue = `${currentValue.slice(0, start)}${displayToken}${currentValue.slice(end)}`;
    targetDisplayRef.value = nextValue;

    requestAnimationFrame(() => {
        target.focus();
        const caret = start + displayToken.length;
        target.setSelectionRange(caret, caret);
    });
}

function applyPreset(): void {
    if (!selectedPreset.value) {
        return;
    }

    const payload = selectedPreset.value.payload;

    props.form.activity_type = payload.activity_type;
    props.form.activity_priority = payload.activity_priority;
    props.form.activity_due_in_days = payload.activity_due_in_days;
    props.form.activity_title_template = payload.activity_title_template;
    props.form.activity_description_template = payload.activity_description_template;
    props.form.notification_message = payload.notification_message;
    syncDisplayFromForm();
}

function copyText(text: string): Promise<void> {
    if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        return navigator.clipboard.writeText(text);
    }

    return new Promise((resolve, reject) => {
        try {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(textarea);

            if (!ok) {
                reject(new Error('copy-failed'));
                return;
            }

            resolve();
        } catch (error) {
            reject(error instanceof Error ? error : new Error('copy-failed'));
        }
    });
}

async function copyPreset(): Promise<void> {
    if (!selectedPreset.value) {
        return;
    }

    const payload = selectedPreset.value.payload;
    const text = [
        `Modelo: ${selectedPreset.value.label}`,
        `Tipo: ${payload.activity_type}`,
        `Prioridade: ${payload.activity_priority}`,
        `Prazo (dias): ${payload.activity_due_in_days}`,
        `Título: ${templateToDisplay(payload.activity_title_template)}`,
        `Descrição: ${templateToDisplay(payload.activity_description_template)}`,
        `Notificação: ${templateToDisplay(payload.notification_message)}`,
    ].join('\n');

    try {
        await copyText(text);
        copiedPreset.value = true;
        setTimeout(() => {
            copiedPreset.value = false;
        }, 1500);
    } catch {
        copiedPreset.value = false;
    }
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="space-y-3 rounded-md border p-3">
            <p class="text-sm font-medium">Modelos prontos</p>
            <p class="text-xs text-muted-foreground">Escolhe um modelo para preencher rapidamente os campos.</p>

            <div class="grid gap-3 md:grid-cols-[2fr_1fr_1fr]">
                <select
                    v-model="selectedPresetId"
                    class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                >
                    <option v-for="preset in presets" :key="preset.id" :value="preset.id">
                        {{ preset.label }}
                    </option>
                </select>

                <Button type="button" variant="outline" @click="copyPreset">
                    {{ copiedPreset ? 'Modelo copiado' : 'Copiar modelo' }}
                </Button>

                <Button type="button" @click="applyPreset">
                    Aplicar modelo
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">{{ selectedPreset?.description ?? '-' }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="name">
                <FormItem>
                    <FormLabel>Nome da regra</FormLabel>
                    <FormControl>
                        <Input v-model="form.name" placeholder="Ex.: Negócios sem atividade" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.name }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="status">
                <FormItem>
                    <FormLabel>Estado</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.status"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option value="active">Ativa</option>
                            <option value="paused">Pausada</option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.status }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <FormField name="inactivity_days">
                <FormItem>
                    <FormLabel>Inatividade (dias)</FormLabel>
                    <FormControl>
                        <Input v-model="form.inactivity_days" type="number" min="1" max="180" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.inactivity_days }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="activity_due_in_days">
                <FormItem>
                    <FormLabel>Prazo da atividade (dias)</FormLabel>
                    <FormControl>
                        <Input v-model="form.activity_due_in_days" type="number" min="0" max="60" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.activity_due_in_days }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="activity_type">
                <FormItem>
                    <FormLabel>Tipo de atividade</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.activity_type"
                            class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        >
                            <option value="call">Chamada</option>
                            <option value="task">Tarefa</option>
                            <option value="meeting">Reunião</option>
                            <option value="note">Nota</option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.activity_type }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <FormField name="activity_priority">
            <FormItem>
                <FormLabel>Prioridade</FormLabel>
                <FormControl>
                    <select
                        v-model="form.activity_priority"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                    >
                        <option value="low">Baixa</option>
                        <option value="medium">Média</option>
                        <option value="high">Alta</option>
                    </select>
                </FormControl>
                <FormMessage>{{ form.errors.activity_priority }}</FormMessage>
            </FormItem>
        </FormField>

        <FormField name="activity_title_template">
            <FormItem>
                <FormLabel>Titulo da atividade</FormLabel>
                <FormControl>
                    <Input
                        ref="titleInputRef"
                        v-model="titleTemplateDisplay"
                        placeholder="Ex.: Follow up automático - [Título do negócio]"
                        required
                    />
                </FormControl>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Button
                        v-for="token in placeholderTokens"
                        :key="`title-${token}`"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="insertToken('activity_title_template', token)"
                    >
                        {{ placeholderLabels[token] }}
                    </Button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Preview: {{ previewTemplate(displayToTemplate(titleTemplateDisplay)) }}</p>
                <FormMessage>{{ form.errors.activity_title_template }}</FormMessage>
            </FormItem>
        </FormField>

        <FormField name="activity_description_template">
            <FormItem>
                <FormLabel>Descricao da atividade (opcional)</FormLabel>
                <FormControl>
                    <textarea
                        ref="descriptionTextareaRef"
                        v-model="descriptionTemplateDisplay"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        placeholder="Ex.: Negócio sem atividade há [Dias sem atividade] dias..."
                    />
                </FormControl>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Button
                        v-for="token in placeholderTokens"
                        :key="`desc-${token}`"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="insertToken('activity_description_template', token)"
                    >
                        {{ placeholderLabels[token] }}
                    </Button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Preview: {{ previewTemplate(displayToTemplate(descriptionTemplateDisplay)) }}</p>
                <FormMessage>{{ form.errors.activity_description_template }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="space-y-3 rounded-md border p-3">
            <FormField name="notify_internal">
                <FormItem class="flex items-center justify-between">
                    <div>
                        <FormLabel>Notificação interna</FormLabel>
                        <p class="text-xs text-muted-foreground">Avisa automaticamente o responsável quando a atividade for criada.</p>
                    </div>
                    <FormControl>
                        <input v-model="form.notify_internal" type="checkbox" class="h-4 w-4" />
                    </FormControl>
                </FormItem>
                <FormMessage>{{ form.errors.notify_internal }}</FormMessage>
            </FormField>

            <FormField name="notification_message">
                <FormItem>
                    <FormLabel>Mensagem interna (opcional)</FormLabel>
                    <FormControl>
                        <Input
                            ref="notificationInputRef"
                            v-model="notificationTemplateDisplay"
                            placeholder="Ex.: Nova atividade automática criada para [Título do negócio]"
                        />
                    </FormControl>
                </FormItem>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Button
                        v-for="token in placeholderTokens"
                        :key="`not-${token}`"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="insertToken('notification_message', token)"
                    >
                        {{ placeholderLabels[token] }}
                    </Button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Preview: {{ previewTemplate(displayToTemplate(notificationTemplateDisplay)) }}</p>
                <FormMessage>{{ form.errors.notification_message }}</FormMessage>
            </FormField>
        </div>

        <div class="flex gap-3">
            <Button type="submit" :disabled="props.form.processing">
                {{ submitLabel }}
            </Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/automations/deal-rules">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
