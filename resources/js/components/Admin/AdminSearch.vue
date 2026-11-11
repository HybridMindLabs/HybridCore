<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CornerDownLeft, CreditCard, FileText, Loader2, Newspaper, Search, Server, Users } from '@lucide/vue';
import { computed, nextTick, ref, watch, type Component } from 'vue';
import { useAdminSearch } from '@/composables/useAdminSearch';

type Group = 'pages' | 'users' | 'servers' | 'articles' | 'payments';

interface Result {
    key: string;
    group: Group;
    title: string;
    subtitle?: string;
    badge?: string | null;
    url: string;
}

interface RemoteResult { id: number; title: string; subtitle: string; badge: string | null; url: string }

const GROUPS: { id: Group; label: string; icon: Component }[] = [
    { id: 'pages', label: 'Pages', icon: FileText },
    { id: 'users', label: 'Users', icon: Users },
    { id: 'servers', label: 'Servers', icon: Server },
    { id: 'articles', label: 'News', icon: Newspaper },
    { id: 'payments', label: 'Payments', icon: CreditCard },
];
const FILTERS: ('all' | Group)[] = ['all', ...GROUPS.map((g) => g.id)];

const page = usePage<{
    adminNav?: { heading: string | null; items: { label: string; url: string }[] }[];
    quickActions?: { label: string; url: string; keywords?: string[] }[];
}>();

const { open, hide } = useAdminSearch();
const query = ref('');
const filter = ref<'all' | Group>('all');
const loading = ref(false);
const remote = ref<Record<Exclude<Group, 'pages'>, RemoteResult[]>>({ users: [], servers: [], articles: [], payments: [] });
const active = ref(0);
const input = ref<HTMLInputElement | null>(null);
const list = ref<HTMLElement | null>(null);

const pages = computed<Result[]>(() => {
    const q = query.value.trim().toLowerCase();
    const nav = (page.props.adminNav ?? []).flatMap((s) => s.items.map((i) => ({
        label: i.label, url: i.url, hint: s.heading ?? '', keywords: [] as string[],
    })));
    const quick = (page.props.quickActions ?? []).map((a) => ({ label: a.label, url: a.url, hint: 'Action', keywords: a.keywords ?? [] }));

    return [...nav, ...quick]
        .filter((p) => !q || [p.label, p.hint, ...p.keywords].some((t) => t.toLowerCase().includes(q)))
        .slice(0, q ? 8 : 6)
        .map((p) => ({ key: `pages:${p.url}:${p.label}`, group: 'pages', title: p.label, subtitle: p.hint, url: p.url }));
});

const results = computed<Result[]>(() => {
    const remoteRows = (Object.keys(remote.value) as Exclude<Group, 'pages'>[]).flatMap((g) =>
        remote.value[g].map((r) => ({ key: `${g}:${r.id}`, group: g, title: r.title, subtitle: r.subtitle, badge: r.badge, url: r.url })));

    return [...pages.value, ...remoteRows].filter((r) => filter.value === 'all' || r.group === filter.value);
});

const grouped = computed(() => GROUPS
    .map((g) => ({ ...g, items: results.value.filter((r) => r.group === g.id) }))
    .filter((g) => g.items.length > 0));

const flat = computed(() => grouped.value.flatMap((g) => g.items));

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

async function fetchRemote() {
    controller?.abort();
    const q = query.value.trim();

    if (q.length < 2 || filter.value === 'pages') {
        remote.value = { users: [], servers: [], articles: [], payments: [] };
        loading.value = false;
        return;
    }

    controller = new AbortController();
    loading.value = true;
    try {
        const r = await fetch(route('admin.search', { q, type: filter.value }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });
        if (r.ok) remote.value = await r.json();
    } catch {
        // Aborted by a newer keystroke — the next request owns the results.
    } finally {
        loading.value = false;
    }
}

watch([query, filter], () => {
    active.value = 0;
    if (timer) clearTimeout(timer);
    timer = setTimeout(fetchRemote, 200);
});

watch(open, async (isOpen) => {
    if (!isOpen) return;
    query.value = '';
    filter.value = 'all';
    await nextTick();
    input.value?.focus();
});

function go(result: Result | undefined) {
    if (!result) return;
    hide();
    router.visit(result.url);
}

function move(delta: number) {
    if (!flat.value.length) return;
    active.value = (active.value + delta + flat.value.length) % flat.value.length;
    nextTick(() => list.value?.querySelector(`[data-index="${active.value}"]`)?.scrollIntoView({ block: 'nearest' }));
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
    else if (e.key === 'Enter') { e.preventDefault(); go(flat.value[active.value]); }
    else if (e.key === 'Escape') { e.preventDefault(); hide(); }
    else if (e.key === 'Tab') {
        e.preventDefault();
        const i = FILTERS.indexOf(filter.value);
        filter.value = FILTERS[(i + (e.shiftKey ? -1 : 1) + FILTERS.length) % FILTERS.length];
    }
}

function indexOf(result: Result): number {
    return flat.value.indexOf(result);
}

function filterLabel(f: 'all' | Group): string {
    return f === 'all' ? 'All' : GROUPS.find((g) => g.id === f)!.label;
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[100] flex items-start justify-center pt-[10vh] px-4" @keydown="onKeydown">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @mousedown="hide" />

            <div
                role="dialog"
                aria-modal="true"
                aria-label="Search the admin panel"
                class="relative w-full max-w-2xl rounded-2xl border border-zinc-800 bg-admin-surface shadow-2xl overflow-hidden"
            >
                <div class="flex items-center gap-3 px-4 border-b border-zinc-800">
                    <Search :size="17" :stroke-width="1.75" class="text-zinc-500 shrink-0" />
                    <input
                        ref="input"
                        v-model="query"
                        type="text"
                        placeholder="Search users, servers, news, payments or pages…"
                        class="flex-1 bg-transparent py-4 text-[15px] text-zinc-100 placeholder-zinc-600 outline-none"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="admin-search-results"
                        :aria-activedescendant="flat[active] ? `admin-search-${active}` : undefined"
                    />
                    <Loader2 v-if="loading" :size="16" class="text-zinc-500 animate-spin shrink-0" />
                    <kbd class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-900 border border-zinc-800 text-zinc-500">ESC</kbd>
                </div>

                <div class="flex items-center gap-1.5 px-4 py-2.5 border-b border-zinc-800/70 overflow-x-auto">
                    <button
                        v-for="f in FILTERS"
                        :key="f"
                        type="button"
                        class="px-2.5 py-1 rounded-md text-xs font-medium whitespace-nowrap transition-colors"
                        :class="filter === f ? 'bg-blue-500/15 text-blue-300' : 'text-zinc-500 hover:text-zinc-200 hover:bg-admin-elevated'"
                        :aria-pressed="filter === f"
                        @click="filter = f; input?.focus()"
                    >{{ filterLabel(f) }}</button>
                </div>

                <div id="admin-search-results" ref="list" role="listbox" class="admin-scroll max-h-[55vh] overflow-y-auto py-2">
                    <template v-for="g in grouped" :key="g.id">
                        <p class="px-4 pt-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">{{ g.label }}</p>
                        <button
                            v-for="r in g.items"
                            :id="`admin-search-${indexOf(r)}`"
                            :key="r.key"
                            type="button"
                            role="option"
                            :aria-selected="indexOf(r) === active"
                            :data-index="indexOf(r)"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors"
                            :class="indexOf(r) === active ? 'bg-blue-500/10' : 'hover:bg-admin-elevated'"
                            @mousemove="active = indexOf(r)"
                            @click="go(r)"
                        >
                            <component :is="g.icon" :size="15" :stroke-width="1.75" class="shrink-0" :class="indexOf(r) === active ? 'text-blue-400' : 'text-zinc-500'" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-zinc-100 truncate">{{ r.title }}</span>
                                <span v-if="r.subtitle" class="block text-xs text-zinc-500 truncate">{{ r.subtitle }}</span>
                            </span>
                            <span v-if="r.badge" class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded border border-zinc-700 text-zinc-400 shrink-0">{{ r.badge }}</span>
                            <CornerDownLeft v-if="indexOf(r) === active" :size="14" class="text-zinc-500 shrink-0" />
                        </button>
                    </template>

                    <p v-if="!flat.length && !loading" class="px-4 py-10 text-center text-sm text-zinc-500">
                        {{ query.trim().length < 2 ? 'Type at least 2 characters to search records.' : `Nothing found for “${query.trim()}”.` }}
                    </p>
                </div>

                <div class="flex items-center gap-4 px-4 py-2.5 border-t border-zinc-800/70 text-[11px] text-zinc-500">
                    <span><kbd class="font-mono">↑↓</kbd> navigate</span>
                    <span><kbd class="font-mono">↵</kbd> open</span>
                    <span><kbd class="font-mono">Tab</kbd> filter</span>
                    <span class="ml-auto">Search by name, email, IP, #id or reference</span>
                </div>
            </div>
        </div>
    </Teleport>
</template>
