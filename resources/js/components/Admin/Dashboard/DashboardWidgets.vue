<script setup lang="ts">
import {
    BookOpen, Gift, HeartPulse, LayoutGrid, Megaphone, Paintbrush, Puzzle, Radio, Server, Swords, ThumbsUp, Timer, Trophy, Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, resolveDynamicComponent } from 'vue';
import type { DashboardWidget } from './charts';

/**
 * Widgets registered by core and by enabled extensions (WidgetRegistry).
 * "stat" widgets are drawn here; any other component name is an extension's
 * own Vue component and is rendered with the widget's props.
 */
const props = defineProps<{ widgets: DashboardWidget[]; hidden?: string[] }>();

const icons: Record<string, Component> = { BookOpen, Gift, HeartPulse, Megaphone, Paintbrush, Puzzle, Radio, Server, Swords, ThumbsUp, Timer, Trophy, Users };

// Literal class strings so Tailwind keeps them.
const accents: Record<string, string> = {
    accent: 'bg-blue-500/10 text-blue-400',
    blue: 'bg-blue-500/10 text-blue-400',
    violet: 'bg-violet-500/10 text-violet-400',
    emerald: 'bg-emerald-500/10 text-emerald-400',
    success: 'bg-emerald-500/10 text-emerald-400',
    amber: 'bg-amber-500/10 text-amber-400',
    warning: 'bg-amber-500/10 text-amber-400',
    red: 'bg-rose-500/10 text-rose-400',
    danger: 'bg-rose-500/10 text-rose-400',
    default: 'bg-zinc-500/10 text-zinc-400',
};

const shown = computed(() => props.widgets.filter((w) => !props.hidden?.includes(w.id)));
const stats = computed(() => shown.value.filter((w) => w.component === 'stat'));

// A custom widget whose component is not registered (its extension's assets
// not rebuilt yet) is skipped instead of rendering an unknown element.
const custom = computed(() => shown.value
    .filter((w) => w.component !== 'stat')
    .map((w) => ({ widget: w, view: resolveDynamicComponent(w.component) }))
    .filter((entry) => typeof entry.view !== 'string'));

const text = (value: unknown) => (value === null || value === undefined ? '' : String(value));
</script>

<template>
    <section v-if="stats.length || custom.length" class="mb-6" aria-label="Widgets">
        <div v-if="stats.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            <div v-for="w in stats" :key="w.id"
                class="rounded-xl border border-zinc-800/70 bg-admin-surface px-4 py-3.5 flex items-center gap-3.5">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                    :class="accents[text(w.props.accent)] ?? accents.default" aria-hidden="true">
                    <component :is="icons[text(w.props.icon)] ?? LayoutGrid" :size="16" :stroke-width="1.8" />
                </div>
                <div class="min-w-0">
                    <p class="text-[20px] font-black leading-none text-zinc-100 truncate">{{ text(w.props.value) || '—' }}</p>
                    <p class="text-[11px] font-medium text-zinc-500 mt-1 truncate">{{ text(w.props.label) || w.title }}</p>
                    <p v-if="w.props.description" class="text-[10.5px] text-zinc-500 truncate">{{ text(w.props.description) }}</p>
                </div>
            </div>
        </div>

        <div v-if="custom.length" class="grid grid-cols-1 lg:grid-cols-2 gap-4" :class="stats.length ? 'mt-4' : ''">
            <component :is="entry.view" v-for="entry in custom" :key="entry.widget.id" v-bind="entry.widget.props" :title="entry.widget.title" />
        </div>
    </section>
</template>
