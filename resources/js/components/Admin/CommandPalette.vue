<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Search, CornerDownLeft, Zap, Circle,
    LayoutDashboard, Users, Server, Newspaper, Settings, Shield, Package,
    Globe, BarChart3, DatabaseBackup, BookOpen, Mail, TrendingUp, ThumbsUp, Puzzle,
} from '@lucide/vue';
import type { Component } from 'vue';
import { ref, computed, watch, nextTick } from 'vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';

interface NavItem { label: string; url: string; icon: string }
interface NavSection { heading: string | null; items: NavItem[] }
interface QuickAction { label: string; url: string; icon: string; keywords: string[] }
interface Action { label: string; url: string; icon: string; keywords: string[] }

const page = usePage<{ adminNav?: NavSection[]; quickActions?: QuickAction[] }>();

const iconMap: Record<string, Component> = {
    LayoutDashboard, Users, Server, Newspaper, Settings, Shield, Package,
    Globe, BarChart3, DatabaseBackup, BookOpen, Mail, TrendingUp, ThumbsUp, Puzzle, Zap,
};
function resolveIcon(name: string): Component { return iconMap[name] ?? Circle; }

const open = ref(false);
const query = ref('');
const active = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

const actions = computed<Action[]>(() => {
    const nav = (page.props.adminNav ?? []).flatMap((s) =>
        s.items.map((i) => ({ label: i.label, url: i.url, icon: i.icon, keywords: [] as string[] })));
    const quick = (page.props.quickActions ?? []).map((q) => ({ label: q.label, url: q.url, icon: q.icon, keywords: q.keywords ?? [] }));
    return [...nav, ...quick];
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return actions.value;
    return actions.value.filter((a) =>
        a.label.toLowerCase().includes(q) || a.keywords.some((k) => k.toLowerCase().includes(q)));
});

watch(filtered, () => { active.value = 0; });

function show() {
    open.value = true;
    query.value = '';
    active.value = 0;
    nextTick(() => inputRef.value?.focus());
}
function hide() { open.value = false; }
function go(a: Action | undefined) {
    if (!a) return;
    hide();
    router.visit(a.url);
}
function onKeydown(e: KeyboardEvent) {
    if (!open.value) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); active.value = Math.min(active.value + 1, filtered.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(active.value - 1, 0); }
    else if (e.key === 'Enter') { e.preventDefault(); go(filtered.value[active.value]); }
}

useKeyboardShortcuts({
    'ctrl+k': show,
    'meta+k': show,
    'Escape': () => { if (open.value) hide(); },
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[100] flex items-start justify-center pt-[15vh] px-4" @mousedown.self="hide">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" />
            <div class="relative w-full max-w-lg rounded-2xl border border-zinc-800 bg-[#111113] shadow-2xl overflow-hidden" @keydown="onKeydown">
                <div class="flex items-center gap-3 px-4 py-3 border-b border-zinc-800">
                    <Search :size="16" :stroke-width="1.8" class="text-zinc-500 shrink-0" />
                    <input ref="inputRef" v-model="query" type="text" placeholder="Jump to…"
                        class="flex-1 bg-transparent text-[14px] text-zinc-100 placeholder:text-zinc-600 focus:outline-none" />
                    <kbd class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-800 border border-zinc-700 text-zinc-500">ESC</kbd>
                </div>

                <div class="max-h-[340px] overflow-y-auto py-1.5">
                    <button v-for="(a, i) in filtered" :key="a.url" type="button"
                        class="flex items-center gap-3 w-full px-4 py-2.5 text-left transition-colors"
                        :class="i === active ? 'bg-blue-500/12 text-blue-300' : 'text-zinc-300 hover:bg-zinc-900/60'"
                        @mousemove="active = i" @click="go(a)">
                        <component :is="resolveIcon(a.icon)" :size="15" :stroke-width="1.8" class="shrink-0" :class="i === active ? 'text-blue-400' : 'text-zinc-500'" />
                        <span class="flex-1 text-[13px] font-medium truncate">{{ a.label }}</span>
                        <CornerDownLeft v-if="i === active" :size="13" :stroke-width="2" class="text-zinc-600 shrink-0" />
                    </button>

                    <div v-if="!filtered.length" class="px-4 py-8 text-center text-[13px] text-zinc-600">
                        No actions for "<span class="text-zinc-400">{{ query }}</span>"
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
