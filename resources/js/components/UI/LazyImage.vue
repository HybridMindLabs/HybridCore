<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const props = withDefaults(defineProps<{
    src: string | null;
    alt?: string;
    class?: string;
    fallback?: string; // initials/placeholder
    fallbackBg?: string;
}>(), { alt: '', fallback: '?', fallbackBg: '#27272a' });

const loaded = ref(false);
const error = ref(false);
const el = ref<HTMLDivElement | null>(null);
let observer: IntersectionObserver | null = null;
const show = ref(!('IntersectionObserver' in window));

onMounted(() => {
    if (!props.src) { error.value = true; return; }
    if (show.value) return;
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) { show.value = true; observer?.disconnect(); }
    }, { rootMargin: '100px' });
    if (el.value) observer.observe(el.value);
});
onUnmounted(() => observer?.disconnect());
</script>

<template>
    <div ref="el" :class="props.class" style="overflow:hidden">
        <img v-if="show && src && !error"
            :src="src" :alt="alt"
            class="w-full h-full object-cover transition-opacity duration-300"
            :class="loaded ? 'opacity-100' : 'opacity-0'"
            loading="lazy"
            @load="loaded = true"
            @error="error = true" />
        <div v-else class="w-full h-full flex items-center justify-center text-white font-bold text-sm select-none"
            :style="{ backgroundColor: fallbackBg }">
            {{ fallback }}
        </div>
    </div>
</template>
