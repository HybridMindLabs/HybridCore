<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { proseClasses } from './prose';

defineProps<{ data: Record<string, any>; props: { html: string } }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <!-- Server-rendered from Markdown with raw HTML stripped (SafeMarkdown). -->
    <div v-if="props.html" class="max-w-3xl mx-auto prose prose-sm leading-relaxed" :class="proseClasses(dark)" v-html="props.html" />
</template>
