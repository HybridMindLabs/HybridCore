<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Trash2, Undo2, Newspaper, MessageSquare } from '@lucide/vue';
import { ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface TrashedArticle { id: number; title: string; author: string; deleted_at: string; purge_at: string }
interface TrashedComment { id: number; body: string; author: string; article_title: string | null; deleted_at: string; purge_at: string }
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator<T> { data: T[]; links: PageLink[]; total: number }

const props = defineProps<{
    articles: Paginator<TrashedArticle>;
    comments: Paginator<TrashedComment>;
    retentionDays: number;
}>();

const tabs = [
    { key: 'articles', label: 'Articles', icon: Newspaper,     count: props.articles.total },
    { key: 'comments', label: 'Comments', icon: MessageSquare, count: props.comments.total },
] as const;

const activeTab = ref<typeof tabs[number]['key']>('articles');

function restoreArticle(id: number) {
    router.post(route('admin.trash.articles.restore', id), {}, { preserveScroll: true });
}

function purgeArticle(id: number, title: string) {
    if (!confirm(`Permanently delete "${title}"? This cannot be undone.`)) return;
    router.delete(route('admin.trash.articles.force-delete', id), { preserveScroll: true });
}

function restoreComment(id: number) {
    router.post(route('admin.trash.comments.restore', id), {}, { preserveScroll: true });
}

function purgeComment(id: number) {
    if (!confirm('Permanently delete this comment? This cannot be undone.')) return;
    router.delete(route('admin.trash.comments.force-delete', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Trash" />
    <AdminLayout title="Trash">

        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <div>
                <h1 class="text-[18px] font-black text-zinc-100">Trash</h1>
                <p class="text-[12px] text-zinc-500 mt-0.5">
                    Deleted content stays here for {{ retentionDays }} days, then it is purged automatically.
                </p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-2 mb-4">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex items-center gap-2 px-4 py-2 rounded-xl border text-[13px] font-semibold transition"
                :class="activeTab === tab.key
                    ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                    : 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'"
                @click="activeTab = tab.key"
            >
                <component :is="tab.icon" :size="13" :stroke-width="1.8" />
                {{ tab.label }}
                <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-full bg-zinc-800 text-zinc-500">{{ tab.count }}</span>
            </button>
        </div>

        <!-- ── Articles tab ────────────────────────────────────── -->
        <template v-if="activeTab === 'articles'">
            <div class="rounded-xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div v-if="!articles.data.length" class="flex flex-col items-center justify-center py-16 text-center">
                    <Trash2 :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                    <p class="text-[13px] text-zinc-600">No deleted articles.</p>
                </div>

                <div v-for="article in articles.data" :key="article.id"
                    class="flex items-center gap-3 px-5 py-3.5 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]">
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-bold text-zinc-100 truncate">{{ article.title }}</p>
                        <p class="text-[11px] text-zinc-600 mt-0.5">
                            by {{ article.author }} · deleted {{ article.deleted_at }} ·
                            <span class="text-amber-500/80">purged on {{ article.purge_at }}</span>
                        </p>
                    </div>
                    <button type="button" @click="restoreArticle(article.id)"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-[11px] font-bold hover:bg-emerald-500/20 transition shrink-0">
                        <Undo2 :size="11" :stroke-width="2" /> Restore
                    </button>
                    <button type="button" @click="purgeArticle(article.id, article.title)"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-red-500/30 bg-red-500/10 text-red-400 text-[11px] font-bold hover:bg-red-500/20 transition shrink-0">
                        <Trash2 :size="11" :stroke-width="2" /> Delete forever
                    </button>
                </div>
            </div>

            <div v-if="articles.links.length > 3" class="flex justify-center gap-1 mt-5 flex-wrap">
                <Link v-for="(link, i) in articles.links" :key="i" :href="link.url ?? '#'"
                    class="px-3 py-1.5 rounded-lg border text-[12px] font-semibold transition"
                    :class="link.active
                        ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                        : link.url ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600' : 'border-zinc-800/40 text-zinc-800 pointer-events-none'"
                    v-html="link.label" />
            </div>
        </template>

        <!-- ── Comments tab ────────────────────────────────────── -->
        <template v-if="activeTab === 'comments'">
            <div class="rounded-xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div v-if="!comments.data.length" class="flex flex-col items-center justify-center py-16 text-center">
                    <Trash2 :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                    <p class="text-[13px] text-zinc-600">No deleted comments.</p>
                </div>

                <div v-for="comment in comments.data" :key="comment.id"
                    class="flex items-start gap-3 px-5 py-3.5 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[13px] font-bold text-zinc-100">{{ comment.author }}</span>
                            <span v-if="comment.article_title" class="text-[11px] text-zinc-600 truncate max-w-[300px]">
                                on {{ comment.article_title }}
                            </span>
                        </div>
                        <p class="text-[13px] text-zinc-400 mt-1 line-clamp-2 whitespace-pre-line">{{ comment.body }}</p>
                        <p class="text-[11px] text-zinc-600 mt-1">
                            deleted {{ comment.deleted_at }} ·
                            <span class="text-amber-500/80">purged on {{ comment.purge_at }}</span>
                        </p>
                    </div>
                    <button type="button" @click="restoreComment(comment.id)"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-[11px] font-bold hover:bg-emerald-500/20 transition shrink-0">
                        <Undo2 :size="11" :stroke-width="2" /> Restore
                    </button>
                    <button type="button" @click="purgeComment(comment.id)"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-red-500/30 bg-red-500/10 text-red-400 text-[11px] font-bold hover:bg-red-500/20 transition shrink-0">
                        <Trash2 :size="11" :stroke-width="2" /> Delete forever
                    </button>
                </div>
            </div>

            <div v-if="comments.links.length > 3" class="flex justify-center gap-1 mt-5 flex-wrap">
                <Link v-for="(link, i) in comments.links" :key="i" :href="link.url ?? '#'"
                    class="px-3 py-1.5 rounded-lg border text-[12px] font-semibold transition"
                    :class="link.active
                        ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                        : link.url ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600' : 'border-zinc-800/40 text-zinc-800 pointer-events-none'"
                    v-html="link.label" />
            </div>
        </template>

    </AdminLayout>
</template>
