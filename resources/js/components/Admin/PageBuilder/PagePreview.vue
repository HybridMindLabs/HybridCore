<script setup lang="ts">
import { csrfHeaders } from '@/lib/csrf';
import { onMounted, onUnmounted, ref } from 'vue';
import { X, Loader2 } from '@lucide/vue';
import BlockRenderer from '@/components/PageBlocks/BlockRenderer.vue';
import type { RenderedBlock } from '@/components/PageBlocks/types';
import type { PageBlock } from './types';

const props = defineProps<{ blocks: PageBlock[]; title: string }>();
const emit = defineEmits<{ close: [] }>();

const rendered = ref<RenderedBlock[]>([]);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await fetch(route('admin.pages.preview'), {
            method: 'POST',
            headers: { ...csrfHeaders(), Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ blocks: props.blocks }),
        });
        if (!res.ok) throw new Error();
        rendered.value = (await res.json()).blocks;
    } catch {
        error.value = 'The preview could not be loaded.';
    } finally {
        loading.value = false;
    }
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => {
    load();
    window.addEventListener('keydown', onKey);
});
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex flex-col bg-black/80" role="dialog" aria-modal="true" aria-label="Page preview">
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-zinc-800 bg-[#121214]">
                <p class="text-sm font-semibold text-zinc-100 truncate">Preview — {{ title || 'Untitled page' }}</p>
                <button type="button" class="p-2 rounded-md text-zinc-400 hover:text-white hover:bg-white/10" aria-label="Close preview"
                    @click="emit('close')"><X :size="16" /></button>
            </div>
            <div class="flex-1 overflow-y-auto bg-[#09090b]">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 py-10">
                    <div v-if="loading" class="flex justify-center py-20 text-zinc-500"><Loader2 :size="22" class="animate-spin" /></div>
                    <p v-else-if="error" class="py-20 text-center text-sm text-red-400">{{ error }}</p>
                    <p v-else-if="!rendered.length" class="py-20 text-center text-sm text-zinc-500">Nothing to show yet — add a block.</p>
                    <BlockRenderer v-else :blocks="rendered" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
