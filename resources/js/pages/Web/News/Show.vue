<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { Clock, Eye, Tag, ChevronLeft, ChevronRight, Copy, Check, BookOpen, Calendar, User, MessageSquare, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import ReportButton from '@/components/UI/ReportButton.vue';

interface Category { id: number; name: string; slug: string; color: string }
interface ArticleCard {
    id: number; title: string; slug: string; excerpt: string | null;
    featured_image_url: string | null; reading_time: number; views: number;
    category: Category | null; published_at: string;
}
interface Article {
    id: number; title: string; slug: string; excerpt: string | null;
    body: string; format: string; featured_image_url: string | null;
    reading_time: number; views: number;
    category: Category | null;
    author: { id: number; name: string; username: string | null; avatar: string | null } | null;
    tags: { id: number; name: string; slug: string }[];
    published_at: string; published_at_iso: string;
    meta_title: string; meta_description: string | null; og_image_url: string | null;
    canonical: string;
}

interface Comment {
    id: number; body: string; created_at: string; is_mine: boolean; can_delete: boolean;
    user: { username: string | null; name: string; avatar: string | null };
}

const props = defineProps<{
    article: Article;
    related: ArticleCard[];
    prev: { id: number; title: string; slug: string } | null;
    next: { id: number; title: string; slug: string } | null;
    comments: Comment[];
}>();

// ── Comments ───────────────────────────────────────────────────────────────
const page = usePage<{ auth: { user: { name: string } | null } }>();
const authed = computed(() => !!page.props.auth?.user);

const commentForm = useForm({ body: '' });

function submitComment() {
    if (commentForm.body.trim().length < 2) return;
    commentForm.post(route('news.comments.store', props.article.slug), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset('body'),
    });
}

function deleteComment(commentId: number) {
    router.delete(route('news.comments.destroy', { article: props.article.slug, comment: commentId }), {
        preserveScroll: true,
    });
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

// Word count from body (rough)
const wordCount = computed(() => props.article.body.split(/\s+/).filter(Boolean).length);

// Copy link
const copied = ref(false);
function copyLink() {
    navigator.clipboard.writeText(props.article.canonical).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    });
}

// Avatar fallback initials
const initials = computed(() => {
    const name = props.article.author?.name ?? '?';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});
</script>

<template>
    <Head>
        <title>{{ article.meta_title }}</title>
        <meta name="description" :content="article.meta_description ?? ''" />
        <meta property="og:title" :content="article.meta_title" />
        <meta property="og:description" :content="article.meta_description ?? ''" />
        <meta v-if="article.og_image_url" property="og:image" :content="article.og_image_url" />
        <meta property="og:type" content="article" />
        <link rel="canonical" :href="article.canonical" />
        <link rel="alternate" type="application/rss+xml" title="News RSS" :href="route('news.feed')" />
    </Head>

    <PublicLayout>

        <!-- ══════════════════════════════════════════════ HERO -->
        <div class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'">

            <!-- Glows + dot grid (same as Home) -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]"
                    :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
                <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
                <div v-if="article.category" class="absolute -top-20 left-0 w-[400px] h-[400px] rounded-full blur-[120px] opacity-[0.06]"
                    :style="{ background: article.category.color }" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-14 sm:py-18">

                <!-- Breadcrumb -->
                <nav class="flex items-center gap-2 text-[12px] mb-7"
                    :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                    <Link :href="route('news.index')" class="transition"
                        :class="dark ? 'hover:text-zinc-300' : 'hover:text-zinc-700'">News</Link>
                    <ChevronRight :size="11" :stroke-width="2" />
                    <Link v-if="article.category" :href="route('news.category', article.category.slug)"
                        class="font-semibold transition" :style="{ color: article.category.color }">
                        {{ article.category.name }}
                    </Link>
                    <ChevronRight v-if="article.category" :size="11" :stroke-width="2" />
                    <span class="truncate max-w-[200px]">{{ article.title }}</span>
                </nav>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-10">

                    <!-- Left: headline block -->
                    <div class="max-w-2xl">
                        <!-- Category badge -->
                        <div v-if="article.category" class="mb-5">
                            <Link :href="route('news.category', article.category.slug)"
                                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest transition"
                                :style="{
                                    color: article.category.color,
                                    borderColor: article.category.color + '40',
                                    backgroundColor: article.category.color + '12'
                                }">
                                <span class="w-1.5 h-1.5 rounded-full" :style="{ background: article.category.color }" />
                                {{ article.category.name }}
                            </Link>
                        </div>

                        <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05] mb-4"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ article.title }}
                        </h1>

                        <p v-if="article.excerpt" class="mt-4 text-[15px] leading-relaxed max-w-lg mb-7"
                            :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                            {{ article.excerpt }}
                        </p>

                        <!-- Stats pills (like Home's stat pills) -->
                        <div class="flex items-center gap-5 flex-wrap mb-7">
                            <div v-for="item in [
                                { icon: Eye,      value: article.views.toLocaleString(), label: 'Views' },
                                { icon: Clock,    value: article.reading_time + ' min',  label: 'Read'  },
                                { icon: BookOpen, value: wordCount.toLocaleString(),      label: 'Words' },
                            ]" :key="item.label" class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                                    :class="dark ? 'bg-zinc-900 border border-zinc-800' : 'bg-white border border-zinc-200 shadow-sm'">
                                    <component :is="item.icon" :size="14" :stroke-width="1.8" class="text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-[16px] font-black leading-none tabular-nums"
                                        :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ item.value }}</p>
                                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5"
                                        :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ item.label }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Author + date -->
                        <div class="flex flex-wrap items-center gap-4">
                            <div v-if="article.author" class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full overflow-hidden border shrink-0 flex items-center justify-center text-[11px] font-bold"
                                    :class="dark ? 'border-zinc-700 bg-zinc-800 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                                    <img v-if="article.author.avatar" :src="article.author.avatar" class="w-full h-full object-cover" />
                                    <span v-else>{{ initials }}</span>
                                </div>
                                <span class="text-[13px] font-semibold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                                    {{ article.author.name }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1 text-[13px]"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                <Calendar :size="12" :stroke-width="1.8" />
                                <time :datetime="article.published_at_iso">{{ article.published_at }}</time>
                            </div>
                        </div>
                    </div>

                    <!-- Right: featured image (like Home's game icon grid) -->
                    <div v-if="article.featured_image_url" class="hidden lg:block shrink-0 w-[420px]">
                        <div class="rounded-2xl overflow-hidden border aspect-[16/10]"
                            :class="dark ? 'border-zinc-800/80' : 'border-zinc-200 shadow-md'">
                            <img :src="article.featured_image_url" class="w-full h-full object-cover" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ══════════════════════════════════════════ END HERO -->

        <!-- ══════════════════════ CONTENT + SIDEBAR -->
        <div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-10">
            <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_280px] gap-10 items-start">

                <!-- ── Article body ── -->
                <article>
                    <div class="prose max-w-none"
                        :class="dark
                            ? 'prose-invert prose-zinc prose-headings:text-zinc-100 prose-headings:font-black prose-p:text-zinc-300 prose-a:text-blue-400 prose-strong:text-zinc-100 prose-code:text-blue-300 prose-code:bg-zinc-900/60 prose-code:text-[13px] prose-pre:bg-[#0d0d0f] prose-pre:border prose-pre:border-zinc-800/70 prose-blockquote:border-l-blue-500/50 prose-blockquote:text-zinc-400 prose-blockquote:bg-zinc-900/30 prose-hr:border-zinc-800/70 prose-img:rounded-xl prose-img:border prose-img:border-zinc-800/70'
                            : 'prose-zinc prose-headings:font-black prose-a:text-blue-600 prose-code:bg-zinc-100 prose-pre:bg-zinc-50 prose-pre:border prose-pre:border-zinc-200 prose-blockquote:border-l-blue-400 prose-img:rounded-xl prose-img:border prose-img:border-zinc-200'"
                        v-html="article.body" />

                    <!-- Tags -->
                    <div v-if="article.tags.length" class="flex flex-wrap gap-2 mt-10 pt-7 border-t"
                        :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
                        <Link v-for="t in article.tags" :key="t.slug"
                            :href="route('news.tag', t.slug)"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[12px] font-semibold transition"
                            :class="dark
                                ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-100 hover:border-zinc-600'
                                : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'">
                            <Tag :size="10" :stroke-width="2" />#{{ t.name }}
                        </Link>
                    </div>

                    <!-- Prev / Next -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-8 pt-7 border-t"
                        :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
                        <Link v-if="prev" :href="route('news.show', prev.slug)"
                            class="flex flex-col gap-1.5 p-4 rounded-xl border transition group"
                            :class="dark
                                ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60'
                                : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-sm'">
                            <span class="text-[11px] font-bold uppercase tracking-widest flex items-center gap-1"
                                :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                                <ChevronLeft :size="11" :stroke-width="2.2" /> Previous
                            </span>
                            <p class="text-[13px] font-semibold line-clamp-2 transition-colors"
                                :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-700 group-hover:text-blue-600'">
                                {{ prev.title }}
                            </p>
                        </Link>
                        <Link v-if="next" :href="route('news.show', next.slug)"
                            class="flex flex-col gap-1.5 p-4 rounded-xl border transition group text-right"
                            :class="[
                                dark ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60' : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-sm',
                                !prev ? 'sm:col-start-2' : ''
                            ]">
                            <span class="text-[11px] font-bold uppercase tracking-widest flex items-center gap-1 justify-end"
                                :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                                Next <ChevronRight :size="11" :stroke-width="2.2" />
                            </span>
                            <p class="text-[13px] font-semibold line-clamp-2 transition-colors"
                                :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-700 group-hover:text-blue-600'">
                                {{ next.title }}
                            </p>
                        </Link>
                    </div>

                    <!-- ── Comments ── -->
                    <div class="mt-8 rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <div class="px-5 py-3.5 border-b flex items-center gap-2"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <MessageSquare :size="13" :stroke-width="1.8" :class="dark ? 'text-zinc-600' : 'text-zinc-400'" />
                            <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ t('news.comments') }}</p>
                            <span class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">({{ comments.length }})</span>
                        </div>

                        <!-- Write comment -->
                        <div v-if="authed" class="px-5 py-4 border-b" :class="dark ? 'border-zinc-800/50' : 'border-zinc-100'">
                            <form class="flex flex-col gap-2.5" @submit.prevent="submitComment">
                                <textarea
                                    v-model="commentForm.body"
                                    rows="3"
                                    maxlength="1000"
                                    :placeholder="t('news.comment_placeholder')"
                                    class="w-full rounded-xl border px-4 py-2.5 text-[13px] resize-none transition focus:outline-none"
                                    :class="dark
                                        ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50'
                                        : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400'"
                                />
                                <p v-if="commentForm.errors.body" class="text-[12px] text-red-400">{{ commentForm.errors.body }}</p>
                                <div>
                                    <button
                                        type="submit"
                                        :disabled="commentForm.processing || commentForm.body.trim().length < 2"
                                        class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-[12px] font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ commentForm.processing ? t('news.posting') : t('news.post_comment') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div v-else class="px-5 py-4 border-b" :class="dark ? 'border-zinc-800/50' : 'border-zinc-100'">
                            <p class="text-[13px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                <Link :href="route('login')" class="font-semibold"
                                    :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'">{{ t('news.login') }}</Link>
                                {{ t('news.comment_login_suffix') }}
                            </p>
                        </div>

                        <!-- Comment list -->
                        <div v-if="comments.length" class="divide-y" :class="dark ? 'divide-zinc-800/50' : 'divide-zinc-100'">
                            <div v-for="comment in comments" :key="comment.id" class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <component
                                        :is="comment.user.username ? Link : 'div'"
                                        :href="comment.user.username ? route('profile.show', comment.user.username) : undefined"
                                        class="w-9 h-9 rounded-xl overflow-hidden shrink-0"
                                    >
                                        <img v-if="comment.user.avatar" :src="comment.user.avatar" class="w-full h-full object-cover" :alt="comment.user.name" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-[13px] font-black text-white uppercase"
                                            :style="{ backgroundColor: avatarBg(comment.user.name) }">{{ comment.user.name[0] }}</div>
                                    </component>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <component
                                                :is="comment.user.username ? Link : 'span'"
                                                :href="comment.user.username ? route('profile.show', comment.user.username) : undefined"
                                                class="text-[13px] font-bold truncate"
                                                :class="[dark ? 'text-zinc-100' : 'text-zinc-800', comment.user.username ? (dark ? 'hover:text-blue-400' : 'hover:text-blue-600') : '']"
                                            >{{ comment.user.name }}</component>
                                            <span class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ comment.created_at }}</span>
                                            <ReportButton v-if="authed && !comment.is_mine" type="comment" :id="comment.id" class="ml-auto" />
                                            <button
                                                v-if="comment.can_delete"
                                                type="button"
                                                class="p-1 rounded transition"
                                                :class="[dark ? 'text-zinc-600 hover:text-red-400' : 'text-zinc-300 hover:text-red-500', (authed && !comment.is_mine) ? '' : 'ml-auto']"
                                                :title="t('news.delete_comment')"
                                                @click="deleteComment(comment.id)"
                                            >
                                                <Trash2 :size="12" :stroke-width="2" />
                                            </button>
                                        </div>
                                        <p class="text-[13px] leading-relaxed mt-1 whitespace-pre-line"
                                            :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ comment.body }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center px-5 py-10 text-center">
                            <MessageSquare :size="22" :stroke-width="1.5" class="mb-2" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
                            <p class="text-[13px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('news.no_comments') }}</p>
                        </div>
                    </div>
                </article>

                <!-- ── Sidebar ── -->
                <aside class="flex flex-col gap-4 sticky top-6">

                    <ExtensionSlot name="news.show.sidebar" :context="{ articleId: article.id }" />

                    <!-- Stats (mobile — also show here) -->
                    <div class="xl:hidden rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="grid grid-cols-4 divide-x"
                            :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-100'">
                            <div v-for="stat in [
                                { label: 'Views',  value: article.views,         icon: Eye },
                                { label: 'Min',    value: article.reading_time,   icon: Clock },
                                { label: 'Words',  value: wordCount,              icon: BookOpen },
                            ]" :key="stat.label"
                                class="flex flex-col items-center justify-center gap-0.5 py-3 px-2 text-center">
                                <component :is="stat.icon" :size="13" :stroke-width="1.5" class="text-blue-400" />
                                <p class="text-[14px] font-black tabular-nums"
                                    :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ stat.value }}</p>
                                <p class="text-[9px] font-bold uppercase tracking-widest"
                                    :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ stat.label }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Author card -->
                    <div v-if="article.author" class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Author</h3>
                        </div>
                        <div class="p-4 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full overflow-hidden border shrink-0 flex items-center justify-center font-bold text-[13px]"
                                :class="dark ? 'border-zinc-700 bg-zinc-800 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                                <img v-if="article.author.avatar" :src="article.author.avatar" class="w-full h-full object-cover" />
                                <span v-else>{{ initials }}</span>
                            </div>
                            <div>
                                <p class="text-[14px] font-bold"
                                    :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ article.author.name }}</p>
                                <p v-if="article.author.username" class="text-[12px]"
                                    :class="dark ? 'text-zinc-600' : 'text-zinc-400'">@{{ article.author.username }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Share -->
                    <div class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Share</h3>
                        </div>
                        <div class="p-3">
                            <button type="button" @click="copyLink"
                                class="w-full flex items-center justify-center gap-2 py-2 rounded-xl border text-[12px] font-semibold transition"
                                :class="copied
                                    ? dark ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400' : 'border-emerald-400/40 bg-emerald-50 text-emerald-600'
                                    : dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'">
                                <component :is="copied ? Check : Copy" :size="12" :stroke-width="2" />
                                {{ copied ? 'Copied!' : 'Copy link' }}
                            </button>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="article.tags.length" class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Tags</h3>
                        </div>
                        <div class="p-3 flex flex-wrap gap-1.5">
                            <Link v-for="t in article.tags" :key="t.slug"
                                :href="route('news.tag', t.slug)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-[11px] font-semibold transition"
                                :class="dark
                                    ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'
                                    : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'">
                                <Tag :size="9" :stroke-width="2" />#{{ t.name }}
                            </Link>
                        </div>
                    </div>

                    <!-- Related articles -->
                    <div v-if="related.length" class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Related</h3>
                        </div>
                        <div class="p-3 flex flex-col gap-1">
                            <Link v-for="a in related" :key="a.id"
                                :href="route('news.show', a.slug)"
                                class="flex gap-3 items-center p-2 rounded-xl transition group"
                                :class="dark ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50'">
                                <div class="w-12 h-12 rounded-lg overflow-hidden border shrink-0"
                                    :class="dark ? 'border-zinc-800/70 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'">
                                    <img v-if="a.featured_image_url" :src="a.featured_image_url" class="w-full h-full object-cover" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[12px] font-semibold line-clamp-2 transition-colors"
                                        :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-700 group-hover:text-blue-600'">
                                        {{ a.title }}
                                    </p>
                                    <p class="text-[11px] mt-0.5 flex items-center gap-1"
                                        :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                                        <Clock :size="9" />{{ a.reading_time }}m
                                    </p>
                                </div>
                            </Link>
                        </div>
                    </div>

                </aside>
            </div>

            <ExtensionSlot name="news.show.bottom" :context="{ articleId: article.id }" />
        </div>

    </PublicLayout>
</template>