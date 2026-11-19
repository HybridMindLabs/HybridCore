<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch, type Component } from 'vue';
import {
    Plus, ChevronUp, ChevronDown, Copy, Trash2, GripVertical, X, Square,
    Sparkles, Type, Image, Images, Columns3, Server, Newspaper, CircleHelp, MousePointerClick, Video, Minus,
} from '@lucide/vue';
import BlockFields from './BlockFields.vue';
import { newBlockId, type BlockType, type GameOption, type PageBlock } from './types';

const blocks = defineModel<PageBlock[]>({ required: true });
const props = defineProps<{ blockTypes: BlockType[]; games: GameOption[] }>();

const ICONS: Record<string, Component> = { Sparkles, Type, Image, Images, Columns3, Server, Newspaper, CircleHelp, MousePointerClick, Video, Minus };
const icon = (name: string) => ICONS[name] ?? Square;

const CATEGORY_LABELS: Record<string, string> = { layout: 'Layout', content: 'Content', media: 'Media', community: 'Community' };

const types = computed(() => Object.fromEntries(props.blockTypes.map((t) => [t.type, t])));
const grouped = computed(() => {
    const groups: Record<string, BlockType[]> = {};
    for (const t of props.blockTypes) (groups[t.category] ??= []).push(t);
    return Object.entries(groups).map(([key, list]) => ({ key, label: CATEGORY_LABELS[key] ?? key, list }));
});

// Block data is plain JSON; structuredClone refuses Vue's reactive proxies.
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value));

const openId = ref<string | null>(blocks.value[0]?.id ?? null);
const picker = ref<{ at: number } | null>(null);

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') picker.value = null;
}

const pickerPanel = ref<HTMLElement | null>(null);
watch(picker, (open) => {
    if (open) nextTick(() => pickerPanel.value?.querySelector<HTMLButtonElement>('[data-block-option]')?.focus());
});
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

function add(type: BlockType) {
    const block: PageBlock = { id: newBlockId(), type: type.type, data: clone(type.defaults) };
    blocks.value.splice(picker.value?.at ?? blocks.value.length, 0, block);
    openId.value = block.id;
    picker.value = null;
}

function move(index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= blocks.value.length) return;
    const list = blocks.value;
    [list[index], list[target]] = [list[target], list[index]];
}

function duplicate(index: number) {
    const copy: PageBlock = { ...clone(blocks.value[index]), id: newBlockId() };
    blocks.value.splice(index + 1, 0, copy);
    openId.value = copy.id;
}

function remove(index: number) {
    const [gone] = blocks.value.splice(index, 1);
    if (openId.value === gone.id) openId.value = null;
}

/** One line under the block name, so a collapsed list is still readable. */
function summary(block: PageBlock): string {
    const d = block.data;
    const text = d.title || d.question || d.caption || d.alt || d.body || d.text || d.url
        || d.items?.[0]?.question || d.columns?.[0]?.title || d.images?.length && `${d.images.length} image(s)` || '';
    return String(text).replace(/[#*_>`[\]()]/g, '').slice(0, 90);
}

// Native drag and drop: the grip starts it, any row is a drop target.
const dragIndex = ref<number | null>(null);
const overIndex = ref<number | null>(null);
function onDrop(index: number) {
    if (dragIndex.value !== null && dragIndex.value !== index) {
        const [moved] = blocks.value.splice(dragIndex.value, 1);
        blocks.value.splice(index, 0, moved);
    }
    dragIndex.value = overIndex.value = null;
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="!blocks.length" class="rounded-xl border border-dashed border-zinc-800 px-6 py-12 text-center">
            <p class="text-sm font-medium text-zinc-300">This page has no blocks yet</p>
            <p class="mt-1 text-xs text-zinc-500">Start with a Hero for the top of the page, then add text, servers or images.</p>
        </div>

        <div v-for="(block, i) in blocks" :key="block.id"
            class="rounded-xl border bg-admin-surface transition-colors"
            :class="[
                openId === block.id ? 'border-blue-500/40' : 'border-zinc-800/70',
                overIndex === i && dragIndex !== i ? 'ring-2 ring-blue-500/40' : '',
                dragIndex === i ? 'opacity-50' : '',
            ]"
            @dragover.prevent="overIndex = i"
            @dragleave="overIndex = overIndex === i ? null : overIndex"
            @drop.prevent="onDrop(i)">
            <div class="flex items-center gap-2 px-3 py-2.5">
                <span draggable="true" class="cursor-grab active:cursor-grabbing p-1 text-zinc-600 hover:text-zinc-300"
                    aria-hidden="true" @dragstart="dragIndex = i" @dragend="dragIndex = overIndex = null">
                    <GripVertical :size="14" />
                </span>
                <button type="button" class="flex flex-1 min-w-0 items-center gap-3 text-left" :aria-expanded="openId === block.id"
                    @click="openId = openId === block.id ? null : block.id">
                    <span class="w-8 h-8 shrink-0 rounded-lg bg-zinc-900 border border-zinc-800/70 flex items-center justify-center text-blue-400">
                        <component :is="icon(types[block.type]?.icon ?? '')" :size="14" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-zinc-100">{{ types[block.type]?.label ?? block.type }}</span>
                        <span class="block truncate text-xs text-zinc-500">{{ summary(block) || 'Empty' }}</span>
                    </span>
                </button>
                <div class="flex items-center gap-0.5 shrink-0">
                    <button type="button" class="p-1.5 rounded-md text-zinc-500 hover:text-zinc-100 hover:bg-white/[0.05] disabled:opacity-30" :disabled="i === 0"
                        aria-label="Move up" title="Move up" @click="move(i, -1)"><ChevronUp :size="14" /></button>
                    <button type="button" class="p-1.5 rounded-md text-zinc-500 hover:text-zinc-100 hover:bg-white/[0.05] disabled:opacity-30" :disabled="i === blocks.length - 1"
                        aria-label="Move down" title="Move down" @click="move(i, 1)"><ChevronDown :size="14" /></button>
                    <button type="button" class="p-1.5 rounded-md text-zinc-500 hover:text-zinc-100 hover:bg-white/[0.05]"
                        aria-label="Duplicate" title="Duplicate" @click="duplicate(i)"><Copy :size="13" /></button>
                    <button type="button" class="p-1.5 rounded-md text-zinc-500 hover:text-red-400 hover:bg-red-500/10"
                        aria-label="Delete block" title="Delete" @click="remove(i)"><Trash2 :size="13" /></button>
                </div>
            </div>

            <div v-if="openId === block.id" class="border-t border-zinc-800/70 px-4 py-4">
                <BlockFields v-if="types[block.type]" :fields="types[block.type].fields" :data="block.data" :games="games" />
                <p v-else class="text-xs text-amber-400">This block comes from an extension that is no longer enabled. It is hidden on the page.</p>
            </div>

            <div class="relative h-0">
                <button type="button"
                    class="absolute left-1/2 -translate-x-1/2 -translate-y-1/2 z-10 w-6 h-6 rounded-full border border-zinc-700 bg-zinc-900 text-zinc-400 hover:text-white hover:border-blue-500 flex items-center justify-center opacity-0 focus-visible:opacity-100 hover:opacity-100 transition-opacity"
                    :aria-label="`Insert block after ${types[block.type]?.label ?? 'block'}`" title="Insert block here"
                    @click="picker = { at: i + 1 }"><Plus :size="12" /></button>
            </div>
        </div>

        <button type="button"
            class="mt-1 inline-flex items-center justify-center gap-2 py-3 rounded-xl border border-dashed border-zinc-700 text-sm font-medium text-zinc-300 hover:text-white hover:border-blue-500/60 hover:bg-blue-500/5 transition-colors"
            @click="picker = { at: blocks.length }">
            <Plus :size="15" /> Add block
        </button>

        <!-- Block picker -->
        <Teleport to="body">
            <div v-if="picker" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-label="Add a block"
                @click.self="picker = null">
                <div ref="pickerPanel" class="w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl border border-zinc-800 bg-[#121214] p-5 shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-zinc-100">Add a block</h2>
                        <button type="button" class="p-1.5 rounded-md text-zinc-500 hover:text-zinc-100" aria-label="Close" @click="picker = null"><X :size="16" /></button>
                    </div>
                    <div v-for="group in grouped" :key="group.key" class="mb-4 last:mb-0">
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">{{ group.label }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <button v-for="t in group.list" :key="t.type" type="button" data-block-option
                                class="flex items-start gap-3 rounded-xl border border-zinc-800/70 p-3 text-left hover:border-blue-500/50 hover:bg-blue-500/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                @click="add(t)">
                                <span class="w-9 h-9 shrink-0 rounded-lg bg-zinc-900 border border-zinc-800/70 flex items-center justify-center text-blue-400">
                                    <component :is="icon(t.icon)" :size="16" />
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold text-zinc-100">{{ t.label }}</span>
                                    <span class="block text-xs text-zinc-500">{{ t.description }}</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
