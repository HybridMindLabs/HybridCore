<script setup lang="ts">
import { toRef } from 'vue';
import { useGameIcon } from '@/composables/useGameIcon';

/**
 * A bare game icon <img> that tries WebP first and falls back through the other
 * formats. Use inside lists where each item needs its own resolution state.
 * When no icon exists at all it renders nothing, leaving whatever the container
 * shows behind it.
 */
const props = withDefaults(defineProps<{
    slug: string;
    alt?: string;
    imgClass?: string;
    size?: 16 | 32 | 64;
}>(), {
    imgClass: 'w-full h-full object-cover',
    size: 64,
});

const { src, onError, failed } = useGameIcon(toRef(props, 'slug'), props.size);
</script>

<template>
    <img
        v-if="!failed"
        :src="src"
        :alt="alt ?? slug"
        :class="imgClass"
        loading="lazy"
        decoding="async"
        @error="onError"
    />
</template>
