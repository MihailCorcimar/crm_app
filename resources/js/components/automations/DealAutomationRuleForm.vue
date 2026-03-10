<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    form: {
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
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="name">
                <FormItem>
                    <FormLabel>Nome da regra</FormLabel>
                    <FormControl>
                        <Input v-model="form.name" placeholder="Ex.: Negocios sem atividade" required />
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
                            <option value="meeting">Reuniao</option>
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
                        <option value="medium">Media</option>
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
                        v-model="form.activity_title_template"
                        placeholder="Ex.: Follow up automatico - {deal_title}"
                        required
                    />
                </FormControl>
                <p class="text-xs text-muted-foreground">
                    Variaveis: {deal_title}, {entity_name}, {owner_name}, {days_without_activity}
                </p>
                <FormMessage>{{ form.errors.activity_title_template }}</FormMessage>
            </FormItem>
        </FormField>

        <FormField name="activity_description_template">
            <FormItem>
                <FormLabel>Descricao da atividade (opcional)</FormLabel>
                <FormControl>
                    <textarea
                        v-model="form.activity_description_template"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none"
                        placeholder="Ex.: Negocio sem atividade ha {days_without_activity} dias..."
                    />
                </FormControl>
                <FormMessage>{{ form.errors.activity_description_template }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="space-y-3 rounded-md border p-3">
            <FormField name="notify_internal">
                <FormItem class="flex items-center justify-between">
                    <div>
                        <FormLabel>Notificacao interna</FormLabel>
                        <p class="text-xs text-muted-foreground">Avisa automaticamente o responsavel quando a atividade for criada.</p>
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
                            v-model="form.notification_message"
                            placeholder="Ex.: Nova atividade automatica criada para {deal_title}"
                        />
                    </FormControl>
                    <FormMessage>{{ form.errors.notification_message }}</FormMessage>
                </FormItem>
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

