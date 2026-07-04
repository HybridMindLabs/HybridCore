<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Eye, Pin, Star, Newspaper, ChevronDown, CheckCircle2, Archive } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, computed } from 'vue';

interface Category { id: number; name: string; color: string }
interface Article {
    id: number; title: string; slug: string; status: string;
    is_scheduled: boolean;
    is_pinned: boolean; is_featured: boolean;
    category: Category | null; author: { id: number; name: string } | null;
    views: number; reading_time: number; published_at: string | null; created_at: string;
}
interface Pagination { data: Article[]; current_page: number; last_page: number; from: number; to: number; total: number }

const props = defineProps<{ articles: Pagination; categories: Category[] }>();

const statusColors: Record<string, string> = {
    published: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400',
    scheduled: 'border-blue-500/30 bg-blue-500/10 text-blue-400',
    draft:     'border-zinc-800/70 bg-zinc-900/50 text-zinc-500',
    archived:  'border-amber-500/30 bg-amber-500/10 text-amber-400',
};

const displayStatus = (a: Article) => (a.is_scheduled ? 'scheduled' : a.status);

function destroy(id: number, title: string) {
    if (!confirm(`Delete "${title}"?`)) return;
    router.delete(route('admin.news.articles.destroy', id));
}

// ── Bulk actions ────────────────────────────────────────────
type BulkAction = 'publish' | 'archive' | 'delete';
const selected = ref<number[]>([]);
const bulkMenuOpen = ref(false);
const bulkPending = ref(false);

const allSelected = computed(() =>
    props.articles.data.length > 0 &&
    props.articles.data.every((a) => selected.value.includes(a.id)),
);

function toggleAll() {
    selected.value = allSelected.value ? [] : props.articles.data.map((a) => a.id);
}

function toggleOne(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((x) => x !== id)
        : [...selected.value, id];
}

function runBulk(action: BulkAction) {
    bulkMenuOpen.value = false;
    if (selected.value.length === 0) return;
    const labels: Record<BulkAction, string> = { publish: 'publish', archive: 'archive', delete: 'permanently delete' };
    if (!confirm(`${labels[action]} ${selected.value.length} selected article(s)?`)) return;
    bulkPending.value = true;
    router.post(route('admin.news.articles.bulk'), { action, article_ids: selected.value }, {
        onFinish: () => { bulkPending.value = false; selected.value = []; },
    });
}
</script>

<template>
    <Head title="News Articles" />
    <AdminLayout title="News Articles">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-[18px] font-black text-zinc-100">Articles</h1>
                <p class="text-[12px] text-zinc-500 mt-0.5">{{ articles.total }} total</p>
            </div>
            <div class="flex items-center gap-2">
                <div v-if="selected.length > 0" class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 border border-zinc-700 bg-zinc-800 text-zinc-200 text-sm font-semibold rounded-lg px-3 py-2 hover:bg-zinc-700 transition-colors"
                        :disabled="bulkPending"
                        @click.stop="bulkMenuOpen = !bulkMenuOpen"
                    >
                        <span>{{ selected.length }} selected</span>
                        <ChevronDown :size="12" :stroke-width="2" :class="bulkMenuOpen ? 'rotate-180' : ''" class="transition-transform" />
                    </button>
                    <div v-if="bulkMenuOpen" class="absolute right-0 top-full mt-1 w-44 bg-zinc-900 border border-zinc-800 rounded-lg shadow-xl py-1 z-50">
                        <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('publish')">
                            <CheckCircle2 :size="13" :stroke-width="1.75" class="text-emerald-400" /> Publish
                        </button>
                        <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('archive')">
                            <Archive :size="13" :stroke-width="1.75" class="text-amber-400" /> Archive
                        </button>
                        <div class="border-t border-zinc-800 my-1" />
                        <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors" @click="runBulk('delete')">
                            <Trash2 :size="13" :stroke-width="1.75" /> Delete
                        </button>
                    </div>
                </div>
                <Link :href="route('admin.news.articles.create')"
                    class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white font-bold text-[13px] px-4 py-2 rounded-xl transition shadow-md shadow-blue-500/20">
                    <Plus :size="14" :stroke-width="2.2" /> New Article
                </Link>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
            <div class="hidden lg:grid grid-cols-[28px_minmax(0,1fr)_120px_100px_80px_70px_80px] gap-3 px-5 py-2.5 border-b border-zinc-800/60 bg-[#1a1a1e] text-[10px] uppercase tracking-wider font-bold text-zinc-600">
                <input type="checkbox" :checked="allSelected" class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer" @change="toggleAll" />
                <span>Title</span><span>Category</span><span>Author</span><span>Views</span><span>Status</span><span />
            </div>

            <div v-if="!articles.data.length" class="flex flex-col items-center py-16 text-center">
                <Newspaper :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                <p class="text-[13px] text-zinc-600">No articles yet.</p>
                <Link :href="route('admin.news.articles.create')" class="text-blue-400 text-[13px] font-semibold mt-2 hover:underline">Write one</Link>
            </div>

            <div v-for="a in articles.data" :key="a.id"
                class="grid grid-cols-1 lg:grid-cols-[28px_minmax(0,1fr)_120px_100px_80px_70px_80px] items-center gap-3 px-5 py-3.5 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]"
                :class="selected.includes(a.id) ? 'bg-blue-500/[0.04]' : ''">
                <input type="checkbox" :checked="selected.includes(a.id)" class="hidden lg:block rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer" @change="toggleOne(a.id)" />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <Pin v-if="a.is_pinned" :size="11" :stroke-width="2" class="text-amber-400 shrink-0" />
                        <Star v-if="a.is_featured" :size="11" :stroke-width="2" class="text-blue-400 shrink-0" />
                        <p class="text-[13px] font-semibold text-zinc-100 truncate">{{ a.title }}</p>
                    </div>
                    <p class="text-[11px] text-zinc-600 font-mono truncate mt-0.5">{{ a.published_at ?? a.created_at }}</p>
                </div>
                <div>
                    <span v-if="a.category" class="inline-flex items-center text-[11px] font-bold px-2 py-0.5 rounded-full"
                        :style="{ backgroundColor: a.category.color+'18', color: a.category.color }">
                        {{ a.category.name }}
                    </span>
                    <span v-else class="text-[11px] text-zinc-700">—</span>
                </div>
                <p class="text-[12px] text-zinc-400 truncate">{{ a.author?.name ?? '—' }}</p>
                <div class="flex items-center gap-1 text-[12px] text-zinc-500">
                    <Eye :size="11" :stroke-width="1.8" />{{ a.views }}
                </div>
                <span class="inline-flex items-center text-[11px] font-bold px-2 py-0.5 rounded-full border" :class="statusColors[displayStatus(a)]">{{ displayStatus(a) }}</span>
                <div class="flex items-center gap-1 justify-end">
                    <Link :href="route('admin.news.articles.edit', a.id)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600 transition">
                        <Pencil :size="12" :stroke-width="1.8" />
                    </Link>
                    <button type="button" @click="destroy(a.id, a.title)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-zinc-800/70 text-zinc-500 hover:text-red-400 hover:bg-red-500/8 hover:border-red-500/20 transition">
                        <Trash2 :size="12" :stroke-width="1.8" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="articles.last_page > 1" class="flex items-center justify-between mt-4">
            <p class="text-[12px] text-zinc-600">{{ articles.from }}–{{ articles.to }} of {{ articles.total }}</p>
            <div class="flex gap-1">
                <Link v-for="p in articles.last_page" :key="p"
                    :href="route('admin.news.articles.index', { page: p })"
                    class="w-8 h-8 flex items-center justify-center rounded-lg border text-[12px] font-bold transition"
                    :class="p === articles.current_page
                        ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                        : 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'">
                    {{ p }}
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>