<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Trash2, MessageSquare, Search, ExternalLink } from '@lucide/vue';
import { ref, watch } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface Comment {
    id: number; body: string; created_at: string;
    user: { username: string | null; name: string };
    article: { title: string; slug: string } | null;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: Comment[]; links: PageLink[]; total: number }

const props = defineProps<{ comments: Paginator; filters: { search: string } }>();

const search = ref(props.filters.search);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.news.comments.index'), value ? { search: value } : {}, { preserveState: true, replace: true });
    }, 350);
});

const selected = ref<number[]>([]);

function toggleSelect(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter(s => s !== id)
        : [...selected.value, id];
}

function toggleSelectAll() {
    selected.value = selected.value.length === props.comments.data.length
        ? []
        : props.comments.data.map(c => c.id);
}

function destroy(id: number) {
    if (!confirm('Delete this comment?')) return;
    router.delete(route('admin.news.comments.destroy', id), { preserveScroll: true });
}

function bulkDelete() {
    if (!selected.value.length) return;
    if (!confirm(`Delete ${selected.value.length} comments?`)) return;
    router.post(route('admin.news.comments.bulk'), { ids: selected.value }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
    });
}
</script>

<template>
    <Head title="News Comments" />
    <AdminLayout title="News Comments">
        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <div>
                <h1 class="text-[18px] font-black text-zinc-100">News Comments</h1>
                <p class="text-[12px] text-zinc-500 mt-0.5">{{ comments.total }} comments</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <Search :size="13" :stroke-width="1.8" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600" />
                    <input v-model="search" type="text" placeholder="Search body or username…"
                        class="w-64 pl-8 pr-3 py-2 rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 text-[13px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 transition" />
                </div>
                <button v-if="selected.length" type="button" @click="bulkDelete"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-red-500/30 bg-red-500/10 text-red-400 text-[12px] font-bold hover:bg-red-500/20 transition">
                    <Trash2 :size="12" :stroke-width="2" /> Delete ({{ selected.length }})
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
            <div v-if="!comments.data.length" class="flex flex-col items-center justify-center py-16 text-center">
                <MessageSquare :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                <p class="text-[13px] text-zinc-600">No comments found.</p>
            </div>

            <template v-else>
                <div class="flex items-center gap-3 px-5 py-2.5 border-b border-zinc-800/60 bg-[#1a1a1e]">
                    <input type="checkbox"
                        :checked="selected.length === comments.data.length && comments.data.length > 0"
                        class="h-4 w-4 rounded border-zinc-700 bg-zinc-900 accent-blue-500"
                        @change="toggleSelectAll" />
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Select all on page</span>
                </div>

                <div v-for="comment in comments.data" :key="comment.id"
                    class="flex items-start gap-3 px-5 py-3.5 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]">
                    <input type="checkbox" :checked="selected.includes(comment.id)"
                        class="h-4 w-4 mt-0.5 rounded border-zinc-700 bg-zinc-900 accent-blue-500 shrink-0"
                        @change="toggleSelect(comment.id)" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[13px] font-bold text-zinc-100">{{ comment.user.username ?? comment.user.name }}</span>
                            <span class="text-[11px] text-zinc-600">{{ comment.created_at }}</span>
                            <a v-if="comment.article" :href="route('news.show', comment.article.slug)" target="_blank"
                                class="inline-flex items-center gap-1 text-[11px] text-zinc-500 hover:text-blue-400 transition truncate max-w-[300px]">
                                <ExternalLink :size="10" :stroke-width="2" /> {{ comment.article.title }}
                            </a>
                        </div>
                        <p class="text-[13px] text-zinc-400 mt-1 line-clamp-2 whitespace-pre-line">{{ comment.body }}</p>
                    </div>
                    <button type="button" @click="destroy(comment.id)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-zinc-800/70 text-zinc-500 hover:text-red-400 hover:bg-red-500/8 hover:border-red-500/20 transition shrink-0">
                        <Trash2 :size="12" :stroke-width="1.8" />
                    </button>
                </div>
            </template>
        </div>

        <!-- Pagination -->
        <div v-if="comments.links.length > 3" class="flex justify-center gap-1 mt-5 flex-wrap">
            <Link v-for="(link, i) in comments.links" :key="i"
                :href="link.url ?? '#'"
                class="px-3 py-1.5 rounded-lg border text-[12px] font-semibold transition"
                :class="link.active
                    ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                    : link.url
                        ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'
                        : 'border-zinc-800/40 text-zinc-800 pointer-events-none'"
                v-html="link.label" />
        </div>
    </AdminLayout>
</template>
