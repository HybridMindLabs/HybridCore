<script setup lang="ts">
import { csrfHeaders } from '@/lib/csrf';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Upload, Search, Loader2, ImageOff, Copy, Check, Trash2, ExternalLink, FolderOpen, RefreshCw, X, CheckSquare } from '@lucide/vue';
import { confirm } from '@/composables/useConfirm';

export interface MediaItem {
    id: number;
    url: string;
    thumb: string;
    name: string;
    folder: string | null;
    alt: string | null;
    size: number;
    width: number | null;
    height: number | null;
    created_at: string | null;
}

interface Usage { type: string; title: string; url: string | null }

const props = withDefaults(defineProps<{ mode?: 'manage' | 'pick'; defaultFolder?: string }>(), { mode: 'manage', defaultFolder: '' });
const emit = defineEmits<{ select: [item: MediaItem] }>();

const inputClass = 'w-full bg-zinc-900/60 border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20';

const items = ref<MediaItem[]>([]);
const folders = ref<string[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const search = ref('');
const folder = ref('');

async function load(reset = true) {
    loading.value = true;
    if (reset) page.value = 1;
    try {
        const params = new URLSearchParams({ page: String(page.value), search: search.value, folder: folder.value });
        const res = await fetch(`${route('admin.media.browse')}?${params}`, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        items.value = reset ? json.media.data : [...items.value, ...json.media.data];
        lastPage.value = json.media.last_page;
        total.value = json.media.total;
        folders.value = json.folders;
    } finally {
        loading.value = false;
    }
}

function more() {
    page.value++;
    load(false);
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => load(), 250);
});
watch(folder, () => load());
const searchInput = ref<HTMLInputElement | null>(null);
onMounted(() => {
    load();
    if (props.mode === 'pick') nextTick(() => searchInput.value?.focus());
});

// ── Upload ────────────────────────────────────────────────────────────────────
const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const uploadError = ref('');
const dragging = ref(false);

async function upload(files: FileList | File[] | null | undefined) {
    const list = Array.from(files ?? []).filter((f) => f.type.startsWith('image/'));
    if (!list.length) return;
    uploading.value = true;
    uploadError.value = '';
    try {
        const fd = new FormData();
        list.slice(0, 20).forEach((f) => fd.append('files[]', f));
        const target = folder.value && folder.value !== '__none' ? folder.value : props.defaultFolder;
        if (target) fd.append('folder', target);
        const res = await fetch(route('admin.media.store'), {
            method: 'POST',
            headers: { ...csrfHeaders(), Accept: 'application/json' },
            body: fd,
        });
        const json = await res.json();
        if (!res.ok) {
            uploadError.value = json.message ?? 'Upload failed.';
            return;
        }
        await load();
        if (props.mode === 'pick' && json.media?.length === 1) emit('select', json.media[0]);
        else if (json.media?.length) selected.value = json.media[0];
    } catch {
        uploadError.value = 'Upload failed.';
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

function onDrop(e: DragEvent) {
    dragging.value = false;
    upload(e.dataTransfer?.files);
}

// ── Details (manage mode) ─────────────────────────────────────────────────────
const selected = ref<MediaItem | null>(null);
const edit = ref({ name: '', alt: '', folder: '' });
const usages = ref<Usage[] | null>(null);
const copied = ref(false);

watch(selected, async (item) => {
    usages.value = null;
    if (!item) return;
    edit.value = { name: item.name, alt: item.alt ?? '', folder: item.folder ?? '' };
    if (props.mode !== 'manage') return;
    const res = await fetch(route('admin.media.usages', item.id), { headers: { Accept: 'application/json' } });
    if (res.ok && selected.value?.id === item.id) usages.value = (await res.json()).usages;
});

function choose(item: MediaItem, e?: MouseEvent) {
    if (props.mode === 'pick') {
        emit('select', item);
        return;
    }
    // Once something is ticked, clicks (and Ctrl/Cmd-clicks any time) build the selection.
    if (checked.value.size || e?.ctrlKey || e?.metaKey) toggle(item, e?.shiftKey);
    else selected.value = item;
}

// ── Multi-select (manage mode) ────────────────────────────────────────────────
const checked = ref(new Set<number>());
let lastToggled: number | null = null;
const notice = ref('');

/** Shift extends the selection from the last ticked image, like a file manager. */
function toggle(item: MediaItem, range = false) {
    const next = new Set(checked.value);
    const index = items.value.findIndex((i) => i.id === item.id);
    const anchor = items.value.findIndex((i) => i.id === lastToggled);
    if (range && anchor !== -1) {
        const [a, b] = anchor < index ? [anchor, index] : [index, anchor];
        items.value.slice(a, b + 1).forEach((i) => next.add(i.id));
    } else if (next.has(item.id)) {
        next.delete(item.id);
    } else {
        next.add(item.id);
    }
    lastToggled = item.id;
    checked.value = next;
}

function selectAll() {
    checked.value = new Set(items.value.map((i) => i.id));
}

function clearSelection() {
    checked.value = new Set();
    lastToggled = null;
}

async function bulkRequest(ids: number[], force: boolean) {
    const res = await fetch(route('admin.media.bulk-destroy'), {
        method: 'POST',
        headers: { ...csrfHeaders(), Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids, force }),
    });
    if (!res.ok) throw new Error();
    return (await res.json()) as { deleted: number; in_use: { id: number; name: string; usages: number }[] };
}

const bulkBusy = ref(false);
async function bulkDelete() {
    const ids = [...checked.value];
    if (!ids.length || !(await confirm(`Delete ${ids.length} image(s)? This cannot be undone.`, { danger: true }))) return;
    bulkBusy.value = true;
    notice.value = '';
    try {
        const first = await bulkRequest(ids, false);
        let deleted = first.deleted;
        if (first.in_use.length) {
            const names = first.in_use.slice(0, 5).map((m) => `"${m.name}"`).join(', ') + (first.in_use.length > 5 ? '…' : '');
            const again = await confirm(
                `Delete ${first.in_use.length} image(s) that are still in use? ${names} — the places using them will show a broken image.`,
                { danger: true, title: 'Some images are in use', confirmLabel: 'Delete anyway', cancelLabel: 'Keep them' },
            );
            if (again) deleted += (await bulkRequest(first.in_use.map((m) => m.id), true)).deleted;
        }
        const kept = ids.length - deleted;
        notice.value = `Deleted ${deleted} image(s)` + (kept ? `, kept ${kept} that are still in use.` : '.');
        clearSelection();
        if (selected.value && ids.includes(selected.value.id)) selected.value = null;
        await load();
    } catch {
        notice.value = 'Deleting failed. Please try again.';
    } finally {
        bulkBusy.value = false;
    }
}

function save() {
    const item = selected.value;
    if (!item) return;
    router.put(route('admin.media.update', item.id), edit.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            Object.assign(item, { name: edit.value.name, alt: edit.value.alt || null, folder: edit.value.folder.trim() || null });
            load();
        },
    });
}

async function destroy() {
    const item = selected.value;
    if (!item) return;
    const used = usages.value?.length ?? 0;
    const message = used
        ? `Delete "${item.name}"? It is still used in ${used} place(s) — those will show a broken image.`
        : `Delete "${item.name}"? This cannot be undone.`;
    if (!(await confirm(message, { danger: true }))) return;
    router.delete(route('admin.media.destroy', item.id), {
        data: used ? { force: 1 } : {},
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            selected.value = null;
            load();
        },
    });
}

function scan() {
    router.post(route('admin.media.scan'), {}, { preserveScroll: true, preserveState: true, onSuccess: () => load() });
}

async function copyUrl() {
    if (!selected.value) return;
    await navigator.clipboard.writeText(new URL(selected.value.url, window.location.origin).href);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

const formatSize = (bytes: number) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const hasMore = computed(() => page.value < lastPage.value);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[200px]">
                <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500" aria-hidden="true" />
                <input ref="searchInput" v-model="search" type="search" :class="inputClass + ' pl-9'" placeholder="Search by name or alt text…" aria-label="Search images" />
            </div>
            <select v-model="folder" :class="inputClass.replace('w-full', 'w-auto')" aria-label="Folder">
                <option value="">All folders</option>
                <option value="__none">No folder</option>
                <option v-for="f in folders" :key="f" :value="f">{{ f }}</option>
            </select>
            <button v-if="mode === 'manage'" type="button"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-zinc-800/70 text-zinc-300 hover:text-white hover:border-zinc-600 transition-colors"
                title="Add images already on the server that the library does not know yet" @click="scan">
                <RefreshCw :size="13" /> Scan for images
            </button>
            <button type="button" :disabled="uploading"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                @click="fileInput?.click()">
                <component :is="uploading ? Loader2 : Upload" :size="13" :class="uploading ? 'animate-spin' : ''" /> Upload
            </button>
            <input ref="fileInput" type="file" multiple accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="upload(($event.target as HTMLInputElement).files)" />
        </div>
        <p v-if="uploadError" class="text-xs text-red-400">{{ uploadError }}</p>

        <!-- Selection bar -->
        <div v-if="mode === 'manage' && checked.size" class="flex flex-wrap items-center gap-2 rounded-lg border border-blue-500/30 bg-blue-500/[0.06] px-3 py-2" role="region" aria-label="Selected images">
            <CheckSquare :size="14" class="text-blue-400" aria-hidden="true" />
            <span class="text-sm font-medium text-zinc-100">{{ checked.size }} selected</span>
            <button type="button" class="px-2 py-1 rounded-md text-xs text-zinc-300 hover:text-white hover:bg-white/[0.06]" @click="selectAll">Select all shown ({{ items.length }})</button>
            <button type="button" class="px-2 py-1 rounded-md text-xs text-zinc-300 hover:text-white hover:bg-white/[0.06]" @click="clearSelection">Clear</button>
            <button type="button" :disabled="bulkBusy"
                class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-500/90 text-white hover:bg-red-500 disabled:opacity-50"
                @click="bulkDelete">
                <component :is="bulkBusy ? Loader2 : Trash2" :size="12" :class="bulkBusy ? 'animate-spin' : ''" /> Delete selected
            </button>
        </div>
        <p v-else-if="mode === 'manage' && items.length" class="text-[11px] text-zinc-600">
            Tip: tick images to delete several at once — Ctrl/Cmd-click to start, Shift-click to select a range.
        </p>
        <p v-if="notice" class="text-xs text-zinc-300" role="status">{{ notice }}</p>

        <div class="grid gap-4" :class="mode === 'manage' && selected ? 'lg:grid-cols-[minmax(0,1fr)_320px]' : ''">
            <!-- Grid + drop zone -->
            <div class="relative rounded-xl border border-dashed p-3 transition-colors"
                :class="dragging ? 'border-blue-500 bg-blue-500/5' : 'border-zinc-800/70'"
                @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="onDrop">
                <div v-if="!items.length && !loading" class="flex flex-col items-center justify-center py-16 text-center">
                    <ImageOff :size="26" class="text-zinc-600 mb-3" />
                    <p class="text-sm font-medium text-zinc-300">{{ search || folder ? 'No images match' : 'No images yet' }}</p>
                    <p class="mt-1 text-xs text-zinc-500">Drop images here or use Upload. They are resized and saved as WebP; animations are kept.</p>
                </div>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2" :class="mode === 'manage' && selected ? 'xl:grid-cols-5' : 'md:grid-cols-6 xl:grid-cols-8'">
                    <div v-for="item in items" :key="item.id" class="group relative aspect-square">
                        <button type="button"
                            class="block w-full h-full overflow-hidden rounded-lg border bg-zinc-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60"
                            :class="checked.has(item.id) ? 'border-blue-500 ring-2 ring-blue-500/50' : selected?.id === item.id ? 'border-blue-500 ring-2 ring-blue-500/40' : 'border-zinc-800/70 hover:border-zinc-600'"
                            :title="item.name" @click="choose(item, $event)">
                            <img :src="item.thumb" :alt="item.alt || item.name" loading="lazy" decoding="async" class="w-full h-full object-cover" />
                            <span class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/80 to-transparent px-1.5 pt-4 pb-1 text-left text-[10px] text-zinc-200 opacity-0 group-hover:opacity-100 transition-opacity">{{ item.name }}</span>
                        </button>
                        <label v-if="mode === 'manage'"
                            class="absolute top-1.5 left-1.5 flex w-6 h-6 items-center justify-center rounded-md bg-black/70 cursor-pointer transition-opacity"
                            :class="checked.size || checked.has(item.id) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 focus-within:opacity-100'">
                            <input type="checkbox" class="w-3.5 h-3.5 rounded border-zinc-500 bg-zinc-900 text-blue-500 focus:ring-blue-500/40"
                                :checked="checked.has(item.id)" :aria-label="`Select ${item.name}`"
                                @click.stop="toggle(item, ($event as MouseEvent).shiftKey)" />
                        </label>
                    </div>
                </div>
                <div v-if="loading" class="flex justify-center py-6 text-zinc-500"><Loader2 :size="18" class="animate-spin" /></div>
                <div v-else-if="hasMore" class="flex justify-center pt-3">
                    <button type="button" class="px-4 py-2 rounded-lg text-xs font-medium border border-zinc-800/70 text-zinc-300 hover:text-white hover:border-zinc-600" @click="more">
                        Load more ({{ items.length }} of {{ total }})
                    </button>
                </div>
            </div>

            <!-- Detail panel -->
            <aside v-if="mode === 'manage' && selected" class="rounded-xl border border-zinc-800/70 bg-admin-surface p-4 flex flex-col gap-4 lg:sticky lg:top-6 lg:self-start">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-zinc-100 break-all">{{ selected.name }}</p>
                    <button type="button" class="p-1 rounded text-zinc-500 hover:text-zinc-100" aria-label="Close details" @click="selected = null"><X :size="15" /></button>
                </div>
                <img :src="selected.url" :alt="selected.alt || ''" class="w-full max-h-56 object-contain rounded-lg bg-zinc-950 border border-zinc-800/70" />
                <p class="text-[11px] text-zinc-500">
                    <template v-if="selected.width">{{ selected.width }}×{{ selected.height }} · </template>{{ formatSize(selected.size) }}
                </p>
                <button type="button" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border border-zinc-800/70 text-zinc-300 hover:text-white hover:border-zinc-600"
                    @click="copyUrl">
                    <component :is="copied ? Check : Copy" :size="12" /> {{ copied ? 'Copied' : 'Copy link' }}
                </button>

                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium" for="media-name">Name</label>
                    <input id="media-name" v-model="edit.name" type="text" maxlength="200" :class="inputClass" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium" for="media-alt">Alt text</label>
                    <input id="media-alt" v-model="edit.alt" type="text" maxlength="255" :class="inputClass" placeholder="Describe the image for screen readers" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium" for="media-folder">Folder</label>
                    <input id="media-folder" v-model="edit.folder" type="text" list="media-folders" maxlength="80" :class="inputClass" placeholder="No folder" />
                    <datalist id="media-folders"><option v-for="f in folders" :key="f" :value="f" /></datalist>
                </div>
                <button type="button" class="px-3 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400" @click="save">Save</button>

                <div>
                    <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-500 mb-2"><FolderOpen :size="12" /> Used on</p>
                    <p v-if="usages === null" class="text-xs text-zinc-600">Checking…</p>
                    <p v-else-if="!usages.length" class="text-xs text-zinc-500">Not used anywhere the library can see.</p>
                    <ul v-else class="flex flex-col gap-1">
                        <li v-for="(u, i) in usages" :key="i">
                            <a v-if="u.url" :href="u.url" class="flex items-center gap-1.5 text-xs text-zinc-300 hover:text-blue-400">
                                <span class="text-zinc-600">{{ u.type }}</span> {{ u.title }} <ExternalLink :size="10" />
                            </a>
                            <span v-else class="flex items-center gap-1.5 text-xs text-zinc-300"><span class="text-zinc-600">{{ u.type }}</span> {{ u.title }}</span>
                        </li>
                    </ul>
                </div>

                <button type="button" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-red-400 border border-red-500/20 hover:bg-red-500/10"
                    @click="destroy">
                    <Trash2 :size="12" /> Delete image
                </button>
            </aside>
        </div>
    </div>
</template>
