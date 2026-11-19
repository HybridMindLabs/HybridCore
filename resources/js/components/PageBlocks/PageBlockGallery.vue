<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { X } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';

const props = defineProps<{ data: Record<string, any> }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const images = computed<any[]>(() => (props.data.images ?? []).filter((i: any) => i.url));
const cols = computed(() => ({ '2': 'sm:grid-cols-2', '3': 'sm:grid-cols-3', '4': 'sm:grid-cols-2 lg:grid-cols-4' }[String(props.data.columns)] ?? 'sm:grid-cols-3'));

const open = ref<number | null>(null);

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') open.value = null;
}
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div v-if="images.length" class="grid grid-cols-2 gap-3" :class="cols">
        <figure v-for="(img, i) in images" :key="i">
            <button type="button" class="block w-full overflow-hidden rounded-xl ring-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60"
                :class="dark ? 'ring-white/[0.06]' : 'ring-zinc-200'"
                :aria-label="img.alt || img.caption || `Image ${i + 1}`"
                @click="open = i">
                <img :src="img.url" :alt="img.alt || ''" loading="lazy" decoding="async"
                    class="w-full aspect-[4/3] object-cover transition-transform duration-300 hover:scale-[1.03] motion-reduce:hover:scale-100" />
            </button>
            <figcaption v-if="img.caption" class="mt-1.5 text-[12.5px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ img.caption }}</figcaption>
        </figure>
    </div>

    <Teleport to="body">
        <div v-if="open !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
            role="dialog" aria-modal="true" @click.self="open = null">
            <button type="button" class="absolute top-4 right-4 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                aria-label="Close" @click="open = null">
                <X :size="18" />
            </button>
            <img :src="images[open].url" :alt="images[open].alt || ''" class="max-h-[88vh] max-w-full rounded-lg" />
        </div>
    </Teleport>
</template>
