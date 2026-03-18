<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

type EntitySelectOption = {
    id: number;
    name: string;
};

type PersonSelectOption = {
    id: number;
    name: string;
    entity_id: number | null;
};

type DealSelectOption = {
    id: number;
    name: string;
    entity_id: number | null;
};

type SimpleSelectOption = {
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
    owners: SimpleSelectOption[];
    eventableTypes: EventableTypeOption[];
    entities: EntitySelectOption[];
    people: PersonSelectOption[];
    deals: DealSelectOption[];
    types: SimpleSelectOption[];
    actions: SimpleSelectOption[];
    submitLabel: string;
}>();

const emit = defineEmits<{
    submit: [];
}>();

const eventableOptions = computed<Array<EntitySelectOption | PersonSelectOption | DealSelectOption>>(() => {
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

const entityPickerIds = ref<number[]>([]);

function normalizeIds(values: Array<number | string>): number[] {
    return [...new Set(
        values
            .map((value) => Number(value))
            .filter((value) => Number.isInteger(value) && value > 0),
    )];
}

const personEntityById = computed<Map<number, number | null>>(
    () => new Map(props.people.map((item) => [item.id, item.entity_id])),
);

const dealEntityById = computed<Map<number, number | null>>(
    () => new Map(props.deals.map((item) => [item.id, item.entity_id])),
);

const selectedEntities = computed<EntitySelectOption[]>(() => {
    const selectedSet = new Set(normalizeIds(props.form.attendee_entity_ids));

    return props.entities.filter((entity) => selectedSet.has(entity.id));
});

const pickedEntitiesCount = computed<number>(() => normalizeIds(entityPickerIds.value).length);

function peopleForEntity(entityId: number): PersonSelectOption[] {
    return props.people.filter((person) => person.entity_id === entityId);
}

function dealsForEntity(entityId: number): DealSelectOption[] {
    return props.deals.filter((deal) => deal.entity_id === entityId);
}

function pruneAttendeesToSelectedEntities(): void {
    const selectedEntitySet = new Set(normalizeIds(props.form.attendee_entity_ids));

    props.form.attendee_person_ids = normalizeIds(props.form.attendee_person_ids).filter((personId) => {
        const entityId = personEntityById.value.get(personId);

        return entityId !== null && entityId !== undefined && selectedEntitySet.has(entityId);
    });

    props.form.attendee_deal_ids = normalizeIds(props.form.attendee_deal_ids).filter((dealId) => {
        const entityId = dealEntityById.value.get(dealId);

        return entityId !== null && entityId !== undefined && selectedEntitySet.has(entityId);
    });
}

function applyEntitySelection(): void {
    props.form.attendee_entity_ids = normalizeIds(entityPickerIds.value);
}

function isPersonChecked(personId: number): boolean {
    return normalizeIds(props.form.attendee_person_ids).includes(personId);
}

function isDealChecked(dealId: number): boolean {
    return normalizeIds(props.form.attendee_deal_ids).includes(dealId);
}

function togglePerson(personId: number, checked: boolean): void {
    const next = new Set(normalizeIds(props.form.attendee_person_ids));

    if (checked) {
        next.add(personId);
    } else {
        next.delete(personId);
    }

    props.form.attendee_person_ids = [...next];
}

function toggleDeal(dealId: number, checked: boolean): void {
    const next = new Set(normalizeIds(props.form.attendee_deal_ids));

    if (checked) {
        next.add(dealId);
    } else {
        next.delete(dealId);
    }

    props.form.attendee_deal_ids = [...next];
}

function selectedPeopleCount(entityId: number): number {
    const peopleIds = new Set(peopleForEntity(entityId).map((item) => item.id));

    return normalizeIds(props.form.attendee_person_ids).filter((item) => peopleIds.has(item)).length;
}

function selectedDealsCount(entityId: number): number {
    const dealIds = new Set(dealsForEntity(entityId).map((item) => item.id));

    return normalizeIds(props.form.attendee_deal_ids).filter((item) => dealIds.has(item)).length;
}

watch(
    () => props.form.attendee_entity_ids,
    (values) => {
        entityPickerIds.value = normalizeIds(values);
        pruneAttendeesToSelectedEntities();
    },
    { immediate: true, deep: true },
);

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

        <div class="space-y-4">
            <FormField name="attendee_entity_ids">
                <FormItem>
                    <FormLabel>Entidades participantes</FormLabel>
                    <FormControl>
                        <div class="rounded-md border p-3">
                            <details open>
                                <summary class="cursor-pointer text-sm font-medium">
                                    Selecionar entidades ({{ pickedEntitiesCount }})
                                </summary>
                                <div class="mt-3 max-h-44 overflow-y-auto rounded-md border p-2 pr-3">
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <label v-for="entity in entities" :key="entity.id" class="flex items-center gap-2 text-sm">
                                            <input
                                                v-model="entityPickerIds"
                                                type="checkbox"
                                                :value="entity.id"
                                                class="h-4 w-4 rounded border-zinc-300 text-primary"
                                            />
                                            <span>{{ entity.name }}</span>
                                        </label>
                                    </div>
                                </div>
                            </details>

                            <div class="mt-3">
                                <Button type="button" variant="outline" @click="applyEntitySelection">
                                    Escolher entidades
                                </Button>
                            </div>
                        </div>
                    </FormControl>
                    <FormDescription>Seleciona entidades e clica em "Escolher entidades".</FormDescription>
                    <FormMessage>{{ form.errors.attendee_entity_ids }}</FormMessage>
                </FormItem>
            </FormField>

            <div v-if="selectedEntities.length === 0" class="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                Sem entidades escolhidas.
            </div>

            <div v-else class="space-y-3">
                <div v-for="entity in selectedEntities" :key="entity.id" class="rounded-md border p-3">
                    <p class="text-sm font-semibold">{{ entity.name }}</p>

                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <details class="rounded-md border p-2">
                            <summary class="cursor-pointer text-sm font-medium">
                                Pessoas ({{ selectedPeopleCount(entity.id) }} selecionada(s))
                            </summary>
                            <div class="mt-2 max-h-44 space-y-2 overflow-auto pr-1">
                                <label
                                    v-for="person in peopleForEntity(entity.id)"
                                    :key="person.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isPersonChecked(person.id)"
                                        class="h-4 w-4 rounded border-zinc-300 text-primary"
                                        @change="togglePerson(person.id, ($event.target as HTMLInputElement).checked)"
                                    />
                                    <span>{{ person.name }}</span>
                                </label>
                                <p v-if="peopleForEntity(entity.id).length === 0" class="text-sm text-muted-foreground">
                                    Sem pessoas para esta entidade.
                                </p>
                            </div>
                        </details>

                        <details class="rounded-md border p-2">
                            <summary class="cursor-pointer text-sm font-medium">
                                Negócios ({{ selectedDealsCount(entity.id) }} selecionado(s))
                            </summary>
                            <div class="mt-2 max-h-44 space-y-2 overflow-auto pr-1">
                                <label
                                    v-for="deal in dealsForEntity(entity.id)"
                                    :key="deal.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isDealChecked(deal.id)"
                                        class="h-4 w-4 rounded border-zinc-300 text-primary"
                                        @change="toggleDeal(deal.id, ($event.target as HTMLInputElement).checked)"
                                    />
                                    <span>{{ deal.name }}</span>
                                </label>
                                <p v-if="dealsForEntity(entity.id).length === 0" class="text-sm text-muted-foreground">
                                    Sem negócios para esta entidade.
                                </p>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
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
