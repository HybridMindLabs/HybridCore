<script setup lang="ts">
import { ChevronUp, ChevronDown, Trash2, Plus } from '@lucide/vue';
import ImageField from './ImageField.vue';
import { emptyItem, type BlockField, type GameOption } from './types';

const props = defineProps<{ fields: BlockField[]; data: Record<string, any>; games: GameOption[] }>();

const inputClass = 'w-full bg-zinc-900/60 border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20';

function items(field: BlockField): Record<string, any>[] {
    if (!Array.isArray(props.data[field.key])) props.data[field.key] = [];
    return props.data[field.key];
}

const singular = (label: string) => label.replace(/s$/, '');

function addItem(field: BlockField) {
    items(field).push(emptyItem(field.fields ?? []));
}

function moveItem(field: BlockField, index: number, delta: number) {
    const list = items(field);
    const target = index + delta;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-for="field in fields" :key="field.key" class="flex flex-col gap-1.5">
            <label v-if="field.type !== 'toggle'" class="text-zinc-400 text-xs font-medium">{{ field.label }}</label>

            <input v-if="field.type === 'text' || field.type === 'url'" v-model="data[field.key]" type="text"
                :maxlength="field.max" :class="inputClass + (field.type === 'url' ? ' font-mono text-xs' : '')"
                :placeholder="field.type === 'url' ? 'https://… or /page' : ''" />

            <textarea v-else-if="field.type === 'textarea'" v-model="data[field.key]" rows="3" :maxlength="field.max" :class="inputClass + ' resize-y'" />

            <template v-else-if="field.type === 'markdown'">
                <textarea v-model="data[field.key]" rows="6" :maxlength="field.max" :class="inputClass + ' resize-y font-mono text-[13px] leading-relaxed'" />
                <p class="text-[11px] text-zinc-600">Markdown: **bold**, *italic*, [link](https://…), - list, ## heading</p>
            </template>

            <ImageField v-else-if="field.type === 'image'" v-model="data[field.key]" :input-class="inputClass"
                @picked="(m) => { if ('alt' in data && !data.alt && m.alt) data.alt = m.alt; }" />

            <input v-else-if="field.type === 'number'" v-model.number="data[field.key]" type="number" :min="field.min" :max="field.max" :class="inputClass + ' w-32'" />

            <select v-else-if="field.type === 'select'" v-model="data[field.key]" :class="inputClass">
                <option v-for="(label, value) in field.options" :key="value" :value="String(value)">{{ label }}</option>
            </select>

            <select v-else-if="field.type === 'game'" v-model="data[field.key]" :class="inputClass">
                <option :value="null">All games</option>
                <option v-for="g in games" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>

            <label v-else-if="field.type === 'toggle'" class="flex items-center gap-2.5 text-sm text-zinc-300 cursor-pointer">
                <input v-model="data[field.key]" type="checkbox" class="w-4 h-4 rounded border-zinc-700 bg-zinc-900 text-blue-500 focus:ring-blue-500/30" />
                {{ field.label }}
            </label>

            <div v-else-if="field.type === 'list'" class="flex flex-col gap-2">
                <div v-for="(item, i) in items(field)" :key="i" class="rounded-lg border border-zinc-800/70 bg-zinc-950/40 p-3">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500">{{ singular(field.label) }} {{ i + 1 }}</span>
                        <div class="flex items-center gap-0.5">
                            <button type="button" class="p-1 rounded text-zinc-500 hover:text-zinc-100 disabled:opacity-30" :disabled="i === 0"
                                aria-label="Move up" @click="moveItem(field, i, -1)"><ChevronUp :size="13" /></button>
                            <button type="button" class="p-1 rounded text-zinc-500 hover:text-zinc-100 disabled:opacity-30" :disabled="i === items(field).length - 1"
                                aria-label="Move down" @click="moveItem(field, i, 1)"><ChevronDown :size="13" /></button>
                            <button type="button" class="p-1 rounded text-zinc-500 hover:text-red-400" aria-label="Remove"
                                @click="items(field).splice(i, 1)"><Trash2 :size="13" /></button>
                        </div>
                    </div>
                    <BlockFields :fields="field.fields ?? []" :data="item" :games="games" />
                </div>
                <button v-if="items(field).length < (field.max_items ?? 24)" type="button"
                    class="inline-flex items-center justify-center gap-1.5 py-2 rounded-lg border border-dashed border-zinc-800 text-xs text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors"
                    @click="addItem(field)">
                    <Plus :size="12" /> Add {{ singular(field.label).toLowerCase() }}
                </button>
            </div>
        </div>
    </div>
</template>
