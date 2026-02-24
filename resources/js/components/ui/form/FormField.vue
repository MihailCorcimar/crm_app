<script setup lang="ts">
import { Field } from 'vee-validate';
import { provide, toRef } from 'vue';
import { FORM_FIELD_INJECTION_KEY } from './injectionKeys';

const props = defineProps<{
    name: string;
}>();

provide(FORM_FIELD_INJECTION_KEY, {
    name: toRef(props, 'name'),
});
</script>

<template>
    <Field :name="name" v-slot="slotProps">
        <slot v-bind="slotProps" />
    </Field>
</template>
