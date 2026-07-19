<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import type { Component } from 'vue';
import { Search, X, User, Server, ArrowRight, Newspaper, ThumbsUp, Gift, Trophy, Puzzle } from '@lucide/vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';

const { t } = useLocale();

const extIcons: Record<string, Component> = { ThumbsUp, Gift, Trophy, Puzzle };
function extIcon(name: string): Component { return extIcons[name] ?? Puzzle; }

const open = ref(false);
const query = ref('');
const loading = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

interface UserResult  { id: number; username: string; display_name: string; avatar: string | null; url: string }
interface ServerResult { id: number; name: string; game: string | null; game_icon: string | null; url: string }
interface ArticleResult { id: number; title: string; excerpt: string | null; url: string }

interface ExtResultRow { title: string; url: string; meta?: string | null }
interface ExtGroup { key: string; label: string; icon: string; results: ExtResultRow[] }

const users    = ref<UserResult[]>([]);
const servers  = ref<ServerResult[]>([]);
const articles = ref<ArticleResult[]>([]);
const extensions = ref<ExtGroup[]>([]);
const hasResults = computed(() => users.value.length > 0 || servers.value.length > 0 || articles.value.length > 0 || extensions.value.length > 0);

let debounce: ReturnType<typeof setTimeout> | null = null;

watch(query, (val) => {
    if (debounce) clearTimeout(debounce);
    if (val.length < 2) { users.value = []; servers.value = []; articles.value = []; extensions.value = []; return; }
    loading.value = true;
    debounce = setTimeout(async () => {
        try {
            const r = await fetch(`/api/search?q=${encodeURIComponent(val)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await r.json();
            users.value    = json.users    ?? [];
            servers.value  = json.servers  ?? [];
            articles.value = json.articles ?? [];
            extensions.value = json.extensions ?? [];
        } finally {
            loading.value = false;
        }
    }, 200);
});

function show() {
    open.value = true;
    query.value = '';
    users.value = [];
    servers.value = [];
    articles.value = [];
    extensions.value = [];
    setTimeout(() => inputRef.value?.focus(), 50);
}
function hide() { open.value = false; }

useKeyboardShortcuts({
    '/': () => show(),
    'Escape': () => hide(),
});
</script>

<template>
    <!-- Trigger hint shown in topbar -->
    <button type="button"
        class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-800 bg-zinc-900/60 text-zinc-500 text-[12px] hover:border-zinc-700 hover:text-zinc-400 transition-colors"
        @click="show">
        <Search :size="12" :stroke-width="1.8" />
        <span>{{ t('navigation.search') }}</span>
        <kbd class="ml-1 px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-500 text-[10px] font-mono border border-zinc-700">/</kbd>
    </button>

    <!-- Overlay -->
    <Teleport to="body">
        <Transition name="search-fade">
            <div v-if="open"
                class="fixed inset-0 z-[10000] flex items-start justify-center pt-[15vh] px-4"
                style="background:rgba(9,9,11,0.85);backdrop-filter:blur(6px)"
                @mousedown.self="hide">

                <div
                    role="dialog"
                    aria-modal="true"
                    :aria-label="t('navigation.search_dialog')"
                    class="w-full max-w-xl bg-[#111113] border border-zinc-800/80 rounded-2xl shadow-2xl overflow-hidden">

                    <!-- Input -->
                    <div class="flex items-center gap-3 px-4 py-3.5 border-b border-zinc-800/60">
                        <Search :size="15" :stroke-width="1.8" class="text-zinc-500 shrink-0" />
                        <label for="global-search" class="sr-only">{{ t('navigation.search') }}</label>
                        <input
                            id="global-search"
                            ref="inputRef"
                            v-model="query"
                            type="search"
                            :placeholder="t('navigation.search_placeholder')"
                            class="flex-1 bg-transparent text-zinc-100 text-[14px] placeholder:text-zinc-500 outline-none" />
                        <div v-if="loading" class="w-4 h-4 rounded-full border-2 border-zinc-700 border-t-blue-500 animate-spin shrink-0" />
                        <button v-else-if="query" type="button" :aria-label="t('navigation.search_clear')"
                            class="text-zinc-400 hover:text-zinc-200 shrink-0" @click="query = ''">
                            <X :size="14" :stroke-width="2" />
                        </button>
                        <kbd v-else class="px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-500 text-[10px] font-mono border border-zinc-700 shrink-0">Esc</kbd>
                    </div>

                    <!-- Results -->
                    <div v-if="hasResults" class="max-h-[360px] overflow-y-auto divide-y divide-zinc-800/50">

                        <!-- Users -->
                        <div v-if="users.length">
                            <p class="px-4 pt-3 pb-1.5 text-[10px] uppercase tracking-widest font-bold text-zinc-500">Users</p>
                            <a v-for="u in users" :key="u.id" :href="u.url"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-zinc-900/60 transition-colors group"
                                @click="hide">
                                <div class="w-7 h-7 rounded-lg overflow-hidden shrink-0 bg-zinc-800 border border-zinc-700 flex items-center justify-center">
                                    <img v-if="u.avatar" :src="u.avatar" class="w-full h-full object-cover" alt="" loading="lazy" decoding="async" />
                                    <User v-else :size="12" :stroke-width="1.8" class="text-zinc-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-zinc-200 text-[13px] font-semibold truncate">{{ u.display_name }}</p>
                                    <p class="text-zinc-500 text-[11px] font-mono">@{{ u.username }}</p>
                                </div>
                                <ArrowRight :size="12" :stroke-width="2" class="text-zinc-500 group-hover:text-zinc-400 shrink-0 transition-colors" />
                            </a>
                        </div>

                        <!-- Servers -->
                        <div v-if="servers.length">
                            <p class="px-4 pt-3 pb-1.5 text-[10px] uppercase tracking-widest font-bold text-zinc-500">Servers</p>
                            <a v-for="s in servers" :key="s.id" :href="s.url"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-zinc-900/60 transition-colors group"
                                @click="hide">
                                <div class="w-7 h-7 rounded-lg overflow-hidden shrink-0 bg-zinc-800 border border-zinc-700 flex items-center justify-center">
                                    <img v-if="s.game_icon" :src="s.game_icon" class="w-full h-full object-cover" alt="" loading="lazy" decoding="async" />
                                    <Server v-else :size="12" :stroke-width="1.8" class="text-zinc-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-zinc-200 text-[13px] font-semibold truncate">{{ s.name }}</p>
                                    <p class="text-zinc-500 text-[11px]">{{ s.game ?? 'Unknown game' }}</p>
                                </div>
                                <ArrowRight :size="12" :stroke-width="2" class="text-zinc-500 group-hover:text-zinc-400 shrink-0 transition-colors" />
                            </a>
                        </div>

                        <!-- News -->
                        <div v-if="articles.length">
                            <p class="px-4 pt-3 pb-1.5 text-[10px] uppercase tracking-widest font-bold text-zinc-500">News</p>
                            <a v-for="a in articles" :key="a.id" :href="a.url"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-zinc-900/60 transition-colors group"
                                @click="hide">
                                <div class="w-7 h-7 rounded-lg shrink-0 bg-zinc-800 border border-zinc-700 flex items-center justify-center">
                                    <Newspaper :size="12" :stroke-width="1.8" class="text-zinc-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-zinc-200 text-[13px] font-semibold truncate">{{ a.title }}</p>
                                    <p v-if="a.excerpt" class="text-zinc-500 text-[11px] truncate">{{ a.excerpt }}</p>
                                </div>
                                <ArrowRight :size="12" :stroke-width="2" class="text-zinc-500 group-hover:text-zinc-400 shrink-0 transition-colors" />
                            </a>
                        </div>

                        <!-- Extension-registered result groups -->
                        <div v-for="group in extensions" :key="group.key">
                            <p class="px-4 pt-3 pb-1.5 text-[10px] uppercase tracking-widest font-bold text-zinc-500">{{ group.label }}</p>
                            <a v-for="(row, i) in group.results" :key="i" :href="row.url"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-zinc-900/60 transition-colors group"
                                @click="hide">
                                <div class="w-7 h-7 rounded-lg shrink-0 bg-zinc-800 border border-zinc-700 flex items-center justify-center">
                                    <component :is="extIcon(group.icon)" :size="12" :stroke-width="1.8" class="text-zinc-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-zinc-200 text-[13px] font-semibold truncate">{{ row.title }}</p>
                                    <p v-if="row.meta" class="text-zinc-500 text-[11px] truncate">{{ row.meta }}</p>
                                </div>
                                <ArrowRight :size="12" :stroke-width="2" class="text-zinc-500 group-hover:text-zinc-400 shrink-0 transition-colors" />
                            </a>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="query.length >= 2 && !loading" class="px-4 py-8 text-center">
                        <p class="text-zinc-500 text-[13px] font-semibold">No results for "<span class="text-zinc-400">{{ query }}</span>"</p>
                    </div>

                    <!-- Hint -->
                    <div v-else-if="!query" class="px-4 py-4 flex items-center gap-4 text-[11px] text-zinc-500">
                        <span>Users, servers…</span>
                        <span class="ml-auto">Press <kbd class="px-1.5 py-0.5 rounded bg-zinc-800 border border-zinc-700 text-zinc-500 font-mono">↑↓</kbd> to navigate</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.search-fade-enter-active { transition: all 0.15s ease-out; }
.search-fade-leave-active { transition: all 0.1s ease-in; }
.search-fade-enter-from, .search-fade-leave-to { opacity: 0; }
.search-fade-enter-from .max-w-xl { transform: scale(0.97) translateY(-8px); }
</style>
