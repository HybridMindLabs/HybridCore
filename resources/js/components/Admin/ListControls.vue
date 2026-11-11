<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bookmark, BookmarkPlus, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{ perPage: number }>();

interface SavedView { name: string; query: string }

const OPTIONS = [15, 25, 50, 100];
const storageKey = `hc-admin-views:${window.location.pathname}`;

const views = ref<SavedView[]>(read());
const naming = ref(false);
const name = ref('');
const nameInput = ref<HTMLInputElement | null>(null);

const sizes = computed(() => [...new Set([...OPTIONS, props.perPage])].sort((a, b) => a - b));
const currentQuery = () => {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    return params.toString();
};

function read(): SavedView[] {
    try {
        return JSON.parse(localStorage.getItem(storageKey) ?? '[]');
    } catch {
        return [];
    }
}

function persist() {
    localStorage.setItem(storageKey, JSON.stringify(views.value));
}

function setPerPage(event: Event) {
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', (event.target as HTMLSelectElement).value);
    params.delete('page');
    router.get(`${window.location.pathname}?${params}`, {}, { preserveScroll: true, preserveState: true, replace: true });
}

async function startSaving() {
    naming.value = true;
    name.value = '';
    await nextTick();
    nameInput.value?.focus();
}

function save() {
    const label = name.value.trim();
    if (!label) return;
    views.value = [...views.value.filter((v) => v.name !== label), { name: label, query: currentQuery() }];
    persist();
    naming.value = false;
}

function open(view: SavedView) {
    router.get(view.query ? `${window.location.pathname}?${view.query}` : window.location.pathname, {}, { preserveScroll: true });
}

function remove(view: SavedView) {
    views.value = views.value.filter((v) => v !== view);
    persist();
}

function isCurrent(view: SavedView): boolean {
    return view.query === currentQuery();
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div v-for="v in views" :key="v.name" class="group inline-flex items-center rounded-md border text-xs transition-colors"
            :class="isCurrent(v) ? 'border-blue-500/40 bg-blue-500/10 text-blue-300' : 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700'">
            <button type="button" class="inline-flex items-center gap-1.5 pl-2 pr-1 py-1" @click="open(v)">
                <Bookmark :size="12" :stroke-width="2" /> {{ v.name }}
            </button>
            <button type="button" class="px-1 py-1 opacity-50 hover:opacity-100" :aria-label="`Remove saved view ${v.name}`" @click="remove(v)">
                <X :size="12" />
            </button>
        </div>

        <form v-if="naming" class="inline-flex items-center gap-1" @submit.prevent="save">
            <label for="saved-view-name" class="sr-only">View name</label>
            <input id="saved-view-name" ref="nameInput" v-model="name" type="text" maxlength="40" placeholder="Name this view…"
                class="w-36 px-2 py-1 rounded-md border border-zinc-700 bg-admin-bg text-xs text-zinc-100 placeholder-zinc-600 outline-none focus:border-blue-500"
                @keydown.esc.prevent="naming = false" />
            <button type="submit" class="px-2 py-1 rounded-md text-xs font-medium bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-50" :disabled="!name.trim()">Save</button>
        </form>
        <button v-else type="button" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs text-zinc-500 hover:text-zinc-200 hover:bg-admin-elevated" @click="startSaving">
            <BookmarkPlus :size="13" :stroke-width="2" /> Save view
        </button>

        <label class="ml-auto inline-flex items-center gap-2 text-xs text-zinc-500">
            Rows
            <select :value="perPage" class="px-2 py-1 rounded-md border border-zinc-800/70 bg-admin-bg text-xs text-zinc-200 outline-none focus:border-zinc-600" @change="setPerPage">
                <option v-for="n in sizes" :key="n" :value="n">{{ n }}</option>
            </select>
        </label>
    </div>
</template>
