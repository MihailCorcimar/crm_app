<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { provide, ref, useId } from 'vue';
import { cn } from '@/lib/utils';
import { FORM_ITEM_INJECTION_KEY } from './injectionKeys';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const id = useId();

provide(FORM_ITEM_INJECTION_KEY, {
    id: ref(id),
});
</script>

<template>
    <div :class="cn('space-y-2', props.class)">
        <slot />
    </div>
</template>
