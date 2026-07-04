<script setup lang="ts">
import { ref, computed } from 'vue';
import { X, Image as ImageIcon } from '@lucide/vue';
import MarkdownEditor from '@/components/Admin/MarkdownEditor.vue';

interface Category { id: number; name: string; color: string }
interface TagOption { id: number; name: string; slug: string }
const props = defineProps<{
    form: any;
    categories: Category[];
    tags: TagOption[];
    submitRoute: string;
    method: 'post' | 'put';
    submitLabel: string;
    uploadRoute?: string;
}>();

const ic  = 'w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 placeholder:text-zinc-700';
const lc  = 'text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5 block';

const tagInput = ref('');
const thumbInput = ref<HTMLInputElement | null>(null);
const thumbDragging = ref(false);
const thumbUploading = ref(false);

async function uploadThumb(file: File) {
    if (!props.uploadRoute) return;
    thumbUploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', file);
        const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        const res = await fetch(props.uploadRoute, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const json = await res.json();
        // Store the disk-relative path, not the absolute URL — an absolute
        // URL bakes in whatever host/port happened to be current at upload
        // time, which breaks (mixed content, stale port) as soon as the
        // environment's URL changes. The model resolves the path to a
        // fresh URL on render.
        if (json.path) props.form.featured_image = json.path;
    } finally {
        thumbUploading.value = false;
        if (thumbInput.value) thumbInput.value.value = '';
    }
}
function handleThumbFile(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) uploadThumb(file);
}
function handleThumbDrop(e: DragEvent) {
    thumbDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file && file.type.startsWith('image/')) uploadThumb(file);
}
// form.featured_image holds a disk-relative path ("news/images/x.png") for
// uploads, or an absolute URL when pasted manually — normalize either into
// something an <img> can actually load.
const thumbPreviewUrl = computed(() => {
    const val = props.form.featured_image;
    if (!val) return '';
    if (/^https?:\/\//.test(val)) return val;
    return `/storage/${val.replace(/^\/?storage\//, '')}`;
});

const addTag = () => {
    const val = tagInput.value.trim().toLowerCase();
    if (val && !props.form.tags.includes(val)) props.form.tags.push(val);
    tagInput.value = '';
};
const removeTag = (t: string) => { props.form.tags = props.form.tags.filter((x: string) => x !== t); };
const tagKeydown = (e: KeyboardEvent) => {
    if (['Enter', ','].includes(e.key)) { e.preventDefault(); addTag(); }
};

const statuses = ['draft', 'published', 'archived'];
const statusColors: Record<string, string> = {
    published: 'text-emerald-400',
    draft:     'text-zinc-500',
    archived:  'text-amber-400',
};

function submit() {
    if (props.method === 'put') props.form.put(props.submitRoute);
    else props.form.post(props.submitRoute);
}
</script>

<template>
    <form @submit.prevent="submit" class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_290px] gap-5 items-start">
        <!-- ── Left: content ───────────────────────────────────────────── -->
        <div class="flex flex-col gap-4">

            <!-- Title -->
            <div class="rounded-2xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div class="border-b border-zinc-800/60 bg-[#1a1a1e] px-5 py-3">
                    <h2 class="text-[13px] font-bold text-zinc-100">Title</h2>
                </div>
                <div class="p-4">
                    <input v-model="form.title"
                        :class="[ic, 'text-[18px] font-bold', form.errors.title ? 'border-red-500/50' : '']"
                        placeholder="Article title..." />
                    <p v-if="form.errors.title" class="text-red-400 text-xs mt-1.5">{{ form.errors.title }}</p>
                    <p class="text-[11px] text-zinc-700 mt-1.5">Slug, excerpt and meta tags are generated automatically.</p>
                </div>
            </div>

            <!-- Markdown editor -->
            <MarkdownEditor v-model="form.body" :upload-route="uploadRoute" />
            <p v-if="form.errors.body" class="text-red-400 text-xs -mt-2">{{ form.errors.body }}</p>

        </div>

        <!-- ── Right: sidebar ──────────────────────────────────────────── -->
        <div class="flex flex-col gap-4">

            <!-- Publish -->
            <div class="rounded-2xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div class="border-b border-zinc-800/60 bg-[#1a1a1e] px-4 py-3">
                    <h2 class="text-[13px] font-bold text-zinc-100">Publish</h2>
                </div>
                <div class="p-4 flex flex-col gap-3">
                    <div>
                        <label :class="lc">Status</label>
                        <select v-model="form.status" :class="[ic, statusColors[form.status] ?? '']">
                            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div>
                        <label :class="lc">Publish Date</label>
                        <input v-model="form.published_at" type="datetime-local" :class="ic" />
                        <p class="text-[10px] text-zinc-700 mt-1">Empty = publish immediately. A future date schedules the article — it goes live automatically at that time.</p>
                    </div>

                    <div class="flex flex-col gap-2 pt-1">
                        <label class="flex items-center gap-2.5 cursor-pointer group">
                            <input v-model="form.is_featured" type="checkbox" class="sr-only peer" />
                            <span class="w-4 h-4 rounded border border-zinc-700 bg-zinc-900/60 flex items-center justify-center peer-checked:bg-blue-500 peer-checked:border-blue-500 transition shrink-0">
                                <svg v-if="form.is_featured" viewBox="0 0 10 8" class="w-2.5 h-2 text-white fill-current"><path d="M1 4l2.5 2.5L9 1"/></svg>
                            </span>
                            <span class="text-[12px] text-zinc-400 group-hover:text-zinc-200">Featured</span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer group">
                            <input v-model="form.is_pinned" type="checkbox" class="sr-only peer" />
                            <span class="w-4 h-4 rounded border border-zinc-700 bg-zinc-900/60 flex items-center justify-center peer-checked:bg-amber-500 peer-checked:border-amber-500 transition shrink-0">
                                <svg v-if="form.is_pinned" viewBox="0 0 10 8" class="w-2.5 h-2 text-white fill-current"><path d="M1 4l2.5 2.5L9 1"/></svg>
                            </span>
                            <span class="text-[12px] text-zinc-400 group-hover:text-zinc-200">Pin to top</span>
                        </label>
                    </div>

                    <div class="pt-2 border-t border-zinc-800/50">
                        <button type="submit" :disabled="form.processing"
                            class="w-full py-2.5 bg-blue-500 hover:bg-blue-600 text-white text-[13px] font-bold rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-60">
                            {{ submitLabel }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Featured image -->
            <div class="rounded-2xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div class="border-b border-zinc-800/60 bg-[#1a1a1e] px-4 py-3">
                    <h2 class="text-[13px] font-bold text-zinc-100">Featured Image</h2>
                </div>
                <div class="p-4 flex flex-col gap-3">
                    <!-- Upload zone -->
                    <div class="relative group cursor-pointer"
                        @click="thumbInput?.click()"
                        @dragover.prevent="thumbDragging = true"
                        @dragleave="thumbDragging = false"
                        @drop.prevent="handleThumbDrop">
                        <!-- Preview -->
                        <div v-if="form.featured_image"
                            class="relative rounded-xl overflow-hidden border border-zinc-800/70 aspect-video">
                            <img :src="thumbPreviewUrl" class="w-full h-full object-cover" />
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                                <span class="text-white text-[12px] font-semibold">Replace image</span>
                            </div>
                            <button type="button" @click.stop="form.featured_image = ''"
                                class="absolute top-2 right-2 w-6 h-6 rounded-full bg-black/70 text-white flex items-center justify-center hover:bg-red-500/80 transition">
                                <X :size="11" :stroke-width="2.5" />
                            </button>
                        </div>
                        <!-- Empty state -->
                        <div v-else
                            class="rounded-xl border-2 border-dashed aspect-video flex flex-col items-center justify-center gap-2 transition"
                            :class="thumbDragging
                                ? 'border-blue-500/60 bg-blue-500/8'
                                : 'border-zinc-800 hover:border-zinc-600 hover:bg-white/[0.02]'">
                            <div class="w-8 h-8 rounded-xl bg-zinc-800/60 flex items-center justify-center">
                                <ImageIcon :size="14" :stroke-width="1.8" class="text-zinc-500" />
                            </div>
                            <p class="text-[12px] font-semibold text-zinc-500">Drop image or click to upload</p>
                            <p class="text-[10px] text-zinc-700">JPG, PNG, WebP · max 5 MB</p>
                        </div>
                    </div>
                    <input ref="thumbInput" type="file" accept="image/*" class="hidden" @change="handleThumbFile" />

                    <p v-if="thumbUploading" class="text-[11px] text-blue-400 flex items-center gap-1.5">
                        <span class="w-3 h-3 border-2 border-blue-400 border-t-transparent rounded-full animate-spin" />
                        Uploading...
                    </p>

                    <!-- Manual URL fallback -->
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-px" :class="'bg-zinc-800/70'" />
                        <span class="text-[10px] text-zinc-700 font-bold">OR PASTE URL</span>
                        <div class="flex-1 h-px" :class="'bg-zinc-800/70'" />
                    </div>
                    <input v-model="form.featured_image" :class="ic" placeholder="https://..." />
                </div>
            </div>

            <!-- Category -->
            <div class="rounded-2xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div class="border-b border-zinc-800/60 bg-[#1a1a1e] px-4 py-3">
                    <h2 class="text-[13px] font-bold text-zinc-100">Category</h2>
                </div>
                <div class="p-4">
                    <select v-model="form.category_id" :class="ic">
                        <option :value="null">No category</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
            </div>

            <!-- Tags -->
            <div class="rounded-2xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div class="border-b border-zinc-800/60 bg-[#1a1a1e] px-4 py-3">
                    <h2 class="text-[13px] font-bold text-zinc-100">Tags</h2>
                </div>
                <div class="p-4 flex flex-col gap-3">
                    <div v-if="form.tags.length" class="flex flex-wrap gap-1.5">
                        <span v-for="t in form.tags" :key="t"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-zinc-800/70 bg-zinc-900/60 text-[11px] font-semibold text-zinc-400">
                            {{ t }}
                            <button type="button" @click="removeTag(t)" class="hover:text-red-400 transition"><X :size="9" :stroke-width="2.5" /></button>
                        </span>
                    </div>
                    <input v-model="tagInput" :class="ic" placeholder="Add tag, press Enter..." @keydown="tagKeydown" @blur="addTag" />
                    <div v-if="tags.length" class="flex flex-wrap gap-1.5">
                        <button v-for="t in tags.filter(t => !form.tags.includes(t.name))" :key="t.id"
                            type="button" @click="form.tags.push(t.name)"
                            class="text-[11px] px-2 py-0.5 rounded-full border border-zinc-800/70 text-zinc-600 hover:text-zinc-300 hover:border-zinc-600 transition">
                            +{{ t.name }}
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
</template>