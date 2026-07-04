<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Flag, CheckCircle, Trash2, ExternalLink, MessageSquare, Star } from '@lucide/vue';
import { ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface Report {
    id: number; type: string; reason: string; details: string | null;
    status: string; created_at: string; reporter: string;
    content: { body: string; author: string; url: string | null } | null;
}
interface Comment {
    id: number; body: string; author: string; created_at: string;
    article_title: string | null; article_slug: string | null;
}
interface Review {
    id: number; rating: number; body: string | null; author: string; created_at: string;
    server_name: string | null; server_url: string | null;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator<T> { data: T[]; links: PageLink[]; total: number }

const props = defineProps<{
    reports: Paginator<Report>;
    comments: Paginator<Comment>;
    reviews: Paginator<Review>;
    counts: { open_reports: number; comments: number; reviews: number };
    filters: { status: string };
}>();

const tabs = [
    { key: 'reports',  label: 'Reports',  icon: Flag,          count: props.counts.open_reports, alert: props.counts.open_reports > 0 },
    { key: 'comments', label: 'Comments', icon: MessageSquare, count: props.counts.comments,     alert: false },
    { key: 'reviews',  label: 'Reviews',  icon: Star,          count: props.counts.reviews,      alert: false },
] as const;

const activeTab = ref<typeof tabs[number]['key']>('reports');

const reasonColors: Record<string, string> = {
    spam: 'text-amber-400 border-amber-500/30 bg-amber-500/10',
    abuse: 'text-red-400 border-red-500/30 bg-red-500/10',
    off_topic: 'text-blue-400 border-blue-500/30 bg-blue-500/10',
    other: 'text-zinc-400 border-zinc-700 bg-zinc-800/50',
};

function setStatus(status: string) {
    router.get(route('admin.moderation.index'), status === 'open' ? {} : { status }, { preserveState: true, replace: true });
}

function resolveReport(id: number) {
    router.post(route('admin.reports.resolve', id), {}, { preserveScroll: true });
}

function destroyReportedContent(id: number) {
    if (!confirm('Delete the reported content? This also resolves all reports pointing at it.')) return;
    router.delete(route('admin.reports.destroy-content', id), { preserveScroll: true });
}

function destroyComment(id: number) {
    if (!confirm('Delete this comment?')) return;
    router.delete(route('admin.news.comments.destroy', id), { preserveScroll: true });
}

function destroyReview(id: number) {
    if (!confirm('Delete this review?')) return;
    router.delete(route('admin.servers.reviews.destroy', id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Moderation" />
    <AdminLayout title="Moderation">

        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <div>
                <h1 class="text-[18px] font-black text-zinc-100">Moderation</h1>
                <p class="text-[12px] text-zinc-500 mt-0.5">Reports, comments and reviews in one queue.</p>
            </div>
            <!-- Status filter (reports tab only) -->
            <div v-if="activeTab === 'reports'" class="flex items-center gap-1">
                <button v-for="s in ['open', 'resolved', 'all']" :key="s" type="button"
                    class="px-3 py-1.5 rounded-lg border text-[12px] font-semibold capitalize transition"
                    :class="filters.status === s
                        ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                        : 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'"
                    @click="setStatus(s)">{{ s }}</button>
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
                <span
                    class="text-[11px] font-bold px-1.5 py-0.5 rounded-full"
                    :class="tab.alert ? 'bg-red-500/20 text-red-400' : 'bg-zinc-800 text-zinc-500'"
                >{{ tab.count }}</span>
            </button>
        </div>

        <!-- ── Reports tab ─────────────────────────────────────── -->
        <template v-if="activeTab === 'reports'">
            <div class="rounded-xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div v-if="!reports.data.length" class="flex flex-col items-center justify-center py-16 text-center">
                    <Flag :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                    <p class="text-[13px] text-zinc-600">No reports — all clear.</p>
                </div>

                <div v-for="report in reports.data" :key="report.id"
                    class="px-5 py-4 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full border"
                                    :class="reasonColors[report.reason] ?? reasonColors.other">{{ report.reason.replace('_', ' ') }}</span>
                                <span class="text-[11px] font-mono text-zinc-600 uppercase">{{ report.type }}</span>
                                <span class="text-[12px] text-zinc-500">reported by <span class="font-semibold text-zinc-300">{{ report.reporter }}</span></span>
                                <span class="text-[11px] text-zinc-600">{{ report.created_at }}</span>
                                <span v-if="report.status === 'resolved'" class="text-[10px] font-bold uppercase tracking-widest text-emerald-500">resolved</span>
                            </div>

                            <div v-if="report.content" class="mt-2 rounded-lg border border-zinc-800/60 bg-zinc-900/40 px-3.5 py-2.5">
                                <p class="text-[12px] text-zinc-400 line-clamp-2 whitespace-pre-line">{{ report.content.body }}</p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="text-[11px] text-zinc-600">by {{ report.content.author }}</span>
                                    <a v-if="report.content.url" :href="report.content.url" target="_blank"
                                        class="inline-flex items-center gap-1 text-[11px] text-zinc-500 hover:text-blue-400 transition">
                                        <ExternalLink :size="10" :stroke-width="2" /> view
                                    </a>
                                </div>
                            </div>
                            <p v-else class="mt-2 text-[12px] italic text-zinc-600">Content already deleted.</p>

                            <p v-if="report.details" class="mt-1.5 text-[12px] text-zinc-500">"{{ report.details }}"</p>
                        </div>

                        <div v-if="report.status === 'open'" class="flex items-center gap-1.5 shrink-0">
                            <button type="button" @click="resolveReport(report.id)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 text-[11px] font-bold hover:bg-emerald-500/20 transition">
                                <CheckCircle :size="11" :stroke-width="2" /> Resolve
                            </button>
                            <button v-if="report.content" type="button" @click="destroyReportedContent(report.id)"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-red-500/30 bg-red-500/10 text-red-400 text-[11px] font-bold hover:bg-red-500/20 transition">
                                <Trash2 :size="11" :stroke-width="2" /> Delete content
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="reports.links.length > 3" class="flex justify-center gap-1 mt-5 flex-wrap">
                <Link v-for="(link, i) in reports.links" :key="i" :href="link.url ?? '#'"
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
                    <MessageSquare :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                    <p class="text-[13px] text-zinc-600">No comments yet.</p>
                </div>

                <div v-for="comment in comments.data" :key="comment.id"
                    class="flex items-start gap-3 px-5 py-3.5 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[13px] font-bold text-zinc-100">{{ comment.author }}</span>
                            <span class="text-[11px] text-zinc-600">{{ comment.created_at }}</span>
                            <a v-if="comment.article_slug" :href="route('news.show', comment.article_slug)" target="_blank"
                                class="inline-flex items-center gap-1 text-[11px] text-zinc-500 hover:text-blue-400 transition truncate max-w-[300px]">
                                <ExternalLink :size="10" :stroke-width="2" /> {{ comment.article_title }}
                            </a>
                        </div>
                        <p class="text-[13px] text-zinc-400 mt-1 line-clamp-2 whitespace-pre-line">{{ comment.body }}</p>
                    </div>
                    <button type="button" @click="destroyComment(comment.id)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-zinc-800/70 text-zinc-500 hover:text-red-400 hover:bg-red-500/8 hover:border-red-500/20 transition shrink-0">
                        <Trash2 :size="12" :stroke-width="1.8" />
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

        <!-- ── Reviews tab ─────────────────────────────────────── -->
        <template v-if="activeTab === 'reviews'">
            <div class="rounded-xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div v-if="!reviews.data.length" class="flex flex-col items-center justify-center py-16 text-center">
                    <Star :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                    <p class="text-[13px] text-zinc-600">No reviews yet.</p>
                </div>

                <div v-for="review in reviews.data" :key="review.id"
                    class="flex items-start gap-3 px-5 py-3.5 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[13px] font-bold text-zinc-100">{{ review.author }}</span>
                            <span class="inline-flex items-center gap-0.5 text-amber-400 text-[12px] font-bold">
                                <Star :size="11" :stroke-width="2" fill="currentColor" /> {{ review.rating }}/5
                            </span>
                            <span class="text-[11px] text-zinc-600">{{ review.created_at }}</span>
                            <a v-if="review.server_url" :href="review.server_url" target="_blank"
                                class="inline-flex items-center gap-1 text-[11px] text-zinc-500 hover:text-blue-400 transition truncate max-w-[300px]">
                                <ExternalLink :size="10" :stroke-width="2" /> {{ review.server_name }}
                            </a>
                            <span v-else-if="review.server_name" class="text-[11px] text-zinc-600">{{ review.server_name }}</span>
                        </div>
                        <p v-if="review.body" class="text-[13px] text-zinc-400 mt-1 line-clamp-2 whitespace-pre-line">{{ review.body }}</p>
                    </div>
                    <button type="button" @click="destroyReview(review.id)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-zinc-800/70 text-zinc-500 hover:text-red-400 hover:bg-red-500/8 hover:border-red-500/20 transition shrink-0">
                        <Trash2 :size="12" :stroke-width="1.8" />
                    </button>
                </div>
            </div>

            <div v-if="reviews.links.length > 3" class="flex justify-center gap-1 mt-5 flex-wrap">
                <Link v-for="(link, i) in reviews.links" :key="i" :href="link.url ?? '#'"
                    class="px-3 py-1.5 rounded-lg border text-[12px] font-semibold transition"
                    :class="link.active
                        ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                        : link.url ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600' : 'border-zinc-800/40 text-zinc-800 pointer-events-none'"
                    v-html="link.label" />
            </div>
        </template>

    </AdminLayout>
</template>
