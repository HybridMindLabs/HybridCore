<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Image, Trash2, Copy, ExternalLink } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface MediaFile {
    filename: string;
    path: string;
    url: string;
    size: number;
    last_modified: number;
}

defineProps<{ files: MediaFile[] }>();

function formatSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function formatDate(ts: number): string {
    return new Date(ts * 1000).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
}

function copyUrl(url: string) {
    navigator.clipboard.writeText(url);
}

function del(filename: string) {
    if (!confirm(`Delete "${filename}"? This cannot be undone.`)) return;
    router.delete(route('admin.news.media.destroy', filename));
}
</script>

<template>
    <Head title="News Media" />
    <AdminLayout title="News Media">

        <PageHeader title="News Media" description="Images uploaded via the news article editor." :icon="Image">
            <template #actions>
                <span class="text-xs text-zinc-600">{{ files.length }} file{{ files.length !== 1 ? 's' : '' }}</span>
            </template>
        </PageHeader>

        <!-- Empty state -->
        <div v-if="files.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-14 h-14 rounded-2xl bg-zinc-900 border border-zinc-800 flex items-center justify-center mb-4">
                <Image :size="22" :stroke-width="1.5" class="text-zinc-600" />
            </div>
            <p class="text-sm font-medium text-zinc-400 mb-1">No images yet</p>
            <p class="text-xs text-zinc-600">Images will appear here when uploaded via the article editor.</p>
        </div>

        <!-- Grid -->
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
            <div
                v-for="file in files"
                :key="file.filename"
                class="group bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden hover:border-zinc-700 transition-colors"
            >
                <!-- Thumbnail -->
                <div class="aspect-square relative overflow-hidden bg-zinc-900">
                    <img
                        :src="file.url"
                        :alt="file.filename"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                    <!-- Hover overlay -->
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <button
                            type="button"
                            class="p-1.5 rounded-lg bg-zinc-800/80 text-zinc-300 hover:text-zinc-100 hover:bg-zinc-700 transition-colors"
                            title="Copy URL"
                            @click="copyUrl(file.url)"
                        >
                            <Copy :size="13" :stroke-width="1.75" />
                        </button>
                        <a
                            :href="file.url"
                            target="_blank"
                            class="p-1.5 rounded-lg bg-zinc-800/80 text-zinc-300 hover:text-zinc-100 hover:bg-zinc-700 transition-colors"
                            title="Open in new tab"
                        >
                            <ExternalLink :size="13" :stroke-width="1.75" />
                        </a>
                        <button
                            type="button"
                            class="p-1.5 rounded-lg bg-red-500/20 text-red-400 hover:bg-red-500/30 transition-colors"
                            title="Delete"
                            @click="del(file.filename)"
                        >
                            <Trash2 :size="13" :stroke-width="1.75" />
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="px-2.5 py-2">
                    <p class="text-xs text-zinc-300 truncate font-mono" :title="file.filename">{{ file.filename }}</p>
                    <div class="flex items-center justify-between mt-0.5">
                        <span class="text-[11px] text-zinc-600">{{ formatSize(file.size) }}</span>
                        <span class="text-[11px] text-zinc-600">{{ formatDate(file.last_modified) }}</span>
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
