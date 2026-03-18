<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <tr
        :class="
            cn(
                'border-b border-zinc-200/70 transition-colors hover:bg-zinc-50/80 data-[state=selected]:bg-zinc-100/70',
                props.class,
            )
        "
    >
        <slot />
    </tr>
</template>
