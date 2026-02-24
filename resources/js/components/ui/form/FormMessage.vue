<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { useSlots } from 'vue';
import { cn } from '@/lib/utils';
import { useFormField } from './useFormField';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const { error, formMessageId } = useFormField();
const slots = useSlots();

const body = computed(() => {
    const message = error.value;
    return message ? String(message) : null;
});
</script>

<template>
    <p
        v-if="body || slots.default"
        :id="formMessageId"
        :class="cn('text-destructive text-sm font-medium', props.class)"
    >
        <template v-if="body">{{ body }}</template>
        <slot v-else />
    </p>
</template>
