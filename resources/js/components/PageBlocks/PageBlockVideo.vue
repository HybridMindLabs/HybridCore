<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

defineProps<{ data: Record<string, any>; props: { embed: string | null } }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <figure v-if="props.embed" class="max-w-4xl mx-auto">
        <div class="relative aspect-video overflow-hidden rounded-xl ring-1" :class="dark ? 'ring-white/[0.06] bg-black' : 'ring-zinc-200 bg-black'">
            <iframe :src="props.embed" :title="data.caption || 'Video'" class="absolute inset-0 w-full h-full" loading="lazy"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                referrerpolicy="strict-origin-when-cross-origin" allowfullscreen />
        </div>
        <figcaption v-if="data.caption" class="mt-2.5 text-center text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ data.caption }}</figcaption>
    </figure>
</template>
