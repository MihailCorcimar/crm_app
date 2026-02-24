<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            d="M3 4a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16H4a1 1 0 0 1-1-1V4Zm3 2v2h2V6H6Zm0 4v2h2v-2H6Zm0 4v2h2v-2H6Zm4-8v2h2V6h-2Zm0 4v2h2v-2h-2Z"
        />
        <path
            fill="currentColor"
            opacity="0.75"
            d="M15 9a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-5V9Z"
        />
        <path
            fill="currentColor"
            d="M16.5 4a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v3h-3V4Z"
        />
    </svg>
</template>
