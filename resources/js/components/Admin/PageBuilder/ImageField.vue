<script setup lang="ts">
import { csrfHeaders } from '@/lib/csrf';
import { ref } from 'vue';
import { Upload, Loader2, X, Images } from '@lucide/vue';
import MediaPicker from '@/components/Admin/Media/MediaPicker.vue';
import type { MediaItem } from '@/components/Admin/Media/MediaBrowser.vue';

const model = defineModel<string>({ default: '' });
defineProps<{ inputClass: string }>();
const emit = defineEmits<{ picked: [item: MediaItem] }>();

const uploading = ref(false);
const error = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const picking = ref(false);

async function upload(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    uploading.value = true;
    error.value = '';
    try {
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch(route('admin.pages.images.upload'), {
            method: 'POST',
            headers: { ...csrfHeaders(), Accept: 'application/json' },
            body: fd,
        });
        const json = await res.json();
        if (res.ok && json.url) model.value = json.url;
        else error.value = json.message ?? 'Upload failed.';
    } catch {
        error.value = 'Upload failed.';
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex gap-2">
            <input v-model="model" type="text" :class="inputClass + ' font-mono text-xs'" placeholder="https://… or /storage/…" />
            <button type="button" :disabled="uploading"
                class="shrink-0 inline-flex items-center gap-1.5 px-3 rounded-lg text-xs font-medium border border-zinc-800/70 text-zinc-300 hover:border-zinc-600 hover:text-zinc-100 transition-colors disabled:opacity-50"
                @click="fileInput?.click()">
                <component :is="uploading ? Loader2 : Upload" :size="12" :class="uploading ? 'animate-spin' : ''" />
                Upload
            </button>
            <button type="button"
                class="shrink-0 inline-flex items-center gap-1.5 px-3 rounded-lg text-xs font-medium border border-zinc-800/70 text-zinc-300 hover:border-zinc-600 hover:text-zinc-100 transition-colors"
                @click="picking = true">
                <Images :size="12" /> Library
            </button>
            <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="upload" />
        </div>
        <div v-if="model" class="relative w-32 h-20 rounded-md overflow-hidden border border-zinc-800/70 bg-zinc-900">
            <img :src="model" alt="" class="w-full h-full object-cover" />
            <button type="button" class="absolute top-1 right-1 w-5 h-5 flex items-center justify-center rounded bg-black/70 text-zinc-200 hover:text-white"
                aria-label="Remove image" @click="model = ''">
                <X :size="11" />
            </button>
        </div>
        <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
        <MediaPicker v-if="picking" default-folder="Pages" @close="picking = false" @select="model = $event.url; emit('picked', $event); picking = false" />
    </div>
</template>
