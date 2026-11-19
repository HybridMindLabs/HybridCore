<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { X } from '@lucide/vue';
import MediaBrowser, { type MediaItem } from './MediaBrowser.vue';

defineProps<{ defaultFolder?: string }>();
const emit = defineEmits<{ select: [item: MediaItem]; close: [] }>();

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-label="Choose an image"
            @click.self="emit('close')">
            <div class="w-full max-w-5xl max-h-[88vh] overflow-y-auto rounded-2xl border border-zinc-800 bg-[#121214] p-5 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-zinc-100">Choose an image</h2>
                    <button type="button" class="p-1.5 rounded-md text-zinc-500 hover:text-zinc-100" aria-label="Close" @click="emit('close')"><X :size="16" /></button>
                </div>
                <MediaBrowser mode="pick" :default-folder="defaultFolder" @select="emit('select', $event)" />
            </div>
        </div>
    </Teleport>
</template>
