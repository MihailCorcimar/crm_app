<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type SelectOption = {
    id: number;
    name: string;
};

type EventableTypeOption = {
    value: 'entity' | 'person' | 'deal';
    label: string;
};

const props = defineProps<{
    form: {
        title: string;
        description: string;
        start_at: string;
        end_at: string;
        location: string;
        owner_id: number | '';
        eventable_type: '' | 'entity' | 'person' | 'deal';
        eventable_id: number | '';
        calendar_type_id: number | '';
        calendar_action_id: number | '';
        attendee_entity_ids: number[];
        attendee_person_ids: number[];
        attendee_deal_ids: number[];
        status: string;
        errors: Record<string, string>;
        processing: boolean;
    };
    owners: SelectOption[];
    eventableTypes: EventableTypeOption[];
    entities: SelectOption[];
    people: SelectOption[];
    deals: SelectOption[];
    types: SelectOption[];
    actions: SelectOption[];
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

const eventableOptions = computed<SelectOption[]>(() => {
    if (props.form.eventable_type === 'entity') {
        return props.entities;
    }

    if (props.form.eventable_type === 'person') {
        return props.people;
    }

    if (props.form.eventable_type === 'deal') {
        return props.deals;
    }

    return [];
});

</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="title">
                <FormItem>
                    <FormLabel>Titulo</FormLabel>
                    <FormControl>
                        <Input v-model="form.title" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.title }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="owner_id">
                <FormItem>
                    <FormLabel>Responsavel</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.owner_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar responsavel</option>
                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                                {{ owner.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.owner_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <FormField name="start_at">
                <FormItem>
                    <FormLabel>Inicio</FormLabel>
                    <FormControl>
                        <Input v-model="form.start_at" type="datetime-local" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.start_at }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="end_at">
                <FormItem>
                    <FormLabel>Fim</FormLabel>
                    <FormControl>
                        <Input v-model="form.end_at" type="datetime-local" required />
                    </FormControl>
                    <FormMessage>{{ form.errors.end_at }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="location">
                <FormItem>
                    <FormLabel>Local</FormLabel>
                    <FormControl>
                        <Input v-model="form.location" placeholder="Opcional" />
                    </FormControl>
                    <FormMessage>{{ form.errors.location }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="eventable_type">
                <FormItem>
                    <FormLabel>Associar a</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.eventable_type"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Sem associacao principal</option>
                            <option v-for="type in eventableTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.eventable_type }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="eventable_id">
                <FormItem>
                    <FormLabel>Registo associado</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.eventable_id"
                            :disabled="form.eventable_type === ''"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar</option>
                            <option v-for="item in eventableOptions" :key="item.id" :value="item.id">
                                {{ item.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.eventable_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <FormField name="calendar_type_id">
                <FormItem>
                    <FormLabel>Tipo</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.calendar_type_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar tipo</option>
                            <option v-for="type in types" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.calendar_type_id }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="calendar_action_id">
                <FormItem>
                    <FormLabel>Ação</FormLabel>
                    <FormControl>
                        <select
                            v-model="form.calendar_action_id"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="''">Selecionar acao</option>
                            <option v-for="action in actions" :key="action.id" :value="action.id">
                                {{ action.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormMessage>{{ form.errors.calendar_action_id }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <FormField name="attendee_entity_ids">
                <FormItem>
                    <FormLabel>Attendees - Entidades</FormLabel>
                    <FormControl>
                        <select
                            multiple
                            v-model="form.attendee_entity_ids"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-28 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option v-for="entity in entities" :key="entity.id" :value="entity.id">
                                {{ entity.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormDescription>Ctrl/Command + clique para selecionar varios.</FormDescription>
                    <FormMessage>{{ form.errors.attendee_entity_ids }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="attendee_person_ids">
                <FormItem>
                    <FormLabel>Attendees - Pessoas</FormLabel>
                    <FormControl>
                        <select
                            multiple
                            v-model="form.attendee_person_ids"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-28 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option v-for="person in people" :key="person.id" :value="person.id">
                                {{ person.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormDescription>Ctrl/Command + clique para selecionar varios.</FormDescription>
                    <FormMessage>{{ form.errors.attendee_person_ids }}</FormMessage>
                </FormItem>
            </FormField>

            <FormField name="attendee_deal_ids">
                <FormItem>
                    <FormLabel>Attendees - Negócios</FormLabel>
                    <FormControl>
                        <select
                            multiple
                            v-model="form.attendee_deal_ids"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-28 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option v-for="deal in deals" :key="deal.id" :value="deal.id">
                                {{ deal.name }}
                            </option>
                        </select>
                    </FormControl>
                    <FormDescription>Ctrl/Command + clique para selecionar varios.</FormDescription>
                    <FormMessage>{{ form.errors.attendee_deal_ids }}</FormMessage>
                </FormItem>
            </FormField>
        </div>

        <FormField name="description">
            <FormItem>
                <FormLabel>Descricao</FormLabel>
                <FormControl>
                    <textarea
                        v-model="form.description"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-24 w-full rounded-md border px-3 py-2 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </FormControl>
                <FormMessage>{{ form.errors.description }}</FormMessage>
            </FormItem>
        </FormField>

        <FormField name="status">
            <FormItem>
                <FormLabel>Estado</FormLabel>
                <FormControl>
                    <select
                        v-model="form.status"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </FormControl>
                <FormDescription>Permite controlar visibilidade da atividade.</FormDescription>
                <FormMessage>{{ form.errors.status }}</FormMessage>
            </FormItem>
        </FormField>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
            <Button type="button" variant="outline" as-child>
                <Link href="/calendar">Cancelar</Link>
            </Button>
        </div>
    </form>
</template>
