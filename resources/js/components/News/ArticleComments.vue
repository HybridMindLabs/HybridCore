<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { MessageSquare, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ReportButton from '@/components/UI/ReportButton.vue';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { avatarBg, type ArticleComment, type CommentPage } from './articleTypes';

const props = defineProps<{ articleSlug: string; comments: CommentPage }>();

const { theme } = useTheme();
const { t, formatDate } = useLocale();
const dark = computed(() => theme.value === 'dark');

const page = usePage<{ auth: { user: unknown } | null }>();
const authed = computed(() => !!page.props.auth?.user);

/**
 * Comments arrive a page at a time. A partial reload replaces the comments
 * prop rather than extending it, so the thread is accumulated here and each
 * fetched page appended.
 */
const visible = ref<ArticleComment[]>([...props.comments.data]);
const loadedPage = ref(props.comments.current_page);
const loadingMore = ref(false);
const hasMore = computed(() => loadedPage.value < props.comments.last_page);

// Posting or deleting reloads the page from the top; start the thread over.
watch(() => props.comments, (next) => {
    if (next.current_page === 1) {
        visible.value = [...next.data];
        loadedPage.value = 1;
    }
});

function loadMore() {
    if (loadingMore.value) return;
    loadingMore.value = true;

    router.get(route('news.show', props.articleSlug), { comments_page: loadedPage.value + 1 }, {
        only: ['comments'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // A comment can shift pages if one was added meanwhile; skip any already shown.
            const seen = new Set(visible.value.map((c) => c.id));
            visible.value.push(...props.comments.data.filter((c) => !seen.has(c.id)));
            loadedPage.value = props.comments.current_page;
        },
        onFinish: () => { loadingMore.value = false; },
    });
}

const MAX_LENGTH = 1000;
const form = useForm({ body: '' });
const canPost = computed(() => form.body.trim().length >= 2 && !form.processing);

function submit() {
    if (!canPost.value) return;
    form.post(route('news.comments.store', props.articleSlug), {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
}

function remove(commentId: number) {
    if (!window.confirm(t('news.delete_comment_confirm'))) return;
    router.delete(route('news.comments.destroy', { article: props.articleSlug, comment: commentId }), { preserveScroll: true });
}

const link = computed(() => dark.value ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700');
const rule = computed(() => dark.value ? 'border-zinc-800/50' : 'border-zinc-100');
</script>

<template>
    <!-- Outside <article>: comments are responses to the piece, not part of it. -->
    <section id="comments" class="scroll-mt-20" :aria-label="t('news.comments')">
        <div class="mt-8 rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
            <div class="px-5 py-3.5 border-b flex items-center gap-2" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                <MessageSquare :size="13" :stroke-width="1.8" class="text-zinc-500" aria-hidden="true" />
                <h2 class="text-[13px] font-semibold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ t('news.comments') }}</h2>
                <span class="text-[11px] text-zinc-500">({{ comments.total }})</span>
            </div>

            <div v-if="authed" class="px-5 py-4 border-b" :class="rule">
                <form class="flex flex-col gap-2.5" @submit.prevent="submit">
                    <label for="comment_body" class="sr-only">{{ t('news.comment_label') }}</label>
                    <textarea id="comment_body" v-model="form.body" rows="3" :maxlength="MAX_LENGTH"
                        :placeholder="t('news.comment_placeholder')"
                        aria-describedby="comment_count"
                        class="w-full rounded-xl border px-4 py-2.5 text-[13px] resize-none transition focus:outline-none"
                        :class="dark
                            ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-500 focus:border-blue-500/50'
                            : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400'" />
                    <p v-if="form.errors.body" class="text-[12px] text-red-400" role="alert">{{ form.errors.body }}</p>
                    <div class="flex items-center justify-between gap-3">
                        <button type="submit" :disabled="!canPost"
                            class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-[12px] font-bold transition disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ form.processing ? t('news.posting') : t('news.post_comment') }}
                        </button>
                        <span id="comment_count" class="text-[11px] tabular-nums shrink-0"
                            :class="form.body.length > MAX_LENGTH - 100 ? (dark ? 'text-amber-400' : 'text-amber-600') : (dark ? 'text-zinc-500' : 'text-zinc-400')">
                            {{ form.body.length }}/{{ MAX_LENGTH }}
                        </span>
                    </div>
                </form>
            </div>
            <div v-else class="px-5 py-4 border-b" :class="rule">
                <p class="text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                    <Link :href="route('login')" class="font-semibold" :class="link">{{ t('news.login') }}</Link>
                    {{ t('news.comment_login_suffix') }}
                </p>
            </div>

            <ul v-if="visible.length" class="divide-y" :class="dark ? 'divide-zinc-800/50' : 'divide-zinc-100'">
                <li v-for="comment in visible" :key="comment.id" class="px-5 py-4">
                    <div class="flex items-start gap-3">
                        <component :is="comment.user.username ? Link : 'div'"
                            :href="comment.user.username ? route('profile.show', comment.user.username) : undefined"
                            class="w-9 h-9 rounded-xl overflow-hidden shrink-0">
                            <img v-if="comment.user.avatar" :src="comment.user.avatar" class="w-full h-full object-cover" alt="" loading="lazy" />
                            <span v-else aria-hidden="true" class="w-full h-full flex items-center justify-center text-[13px] font-black text-white uppercase"
                                :style="{ backgroundColor: avatarBg(comment.user.name) }">{{ (comment.user.name || '?')[0] }}</span>
                        </component>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <component :is="comment.user.username ? Link : 'span'"
                                    :href="comment.user.username ? route('profile.show', comment.user.username) : undefined"
                                    class="text-[13px] font-bold truncate"
                                    :class="[dark ? 'text-zinc-100' : 'text-zinc-800', comment.user.username ? (dark ? 'hover:text-blue-400' : 'hover:text-blue-600') : '']">{{ comment.user.name }}</component>
                                <time :datetime="comment.created_at_iso" class="text-[11px] text-zinc-500">
                                    {{ formatDate(comment.created_at_iso, { dateStyle: 'medium', timeStyle: 'short' }) }}
                                </time>
                                <ReportButton v-if="authed && !comment.is_mine" type="comment" :id="comment.id" class="ml-auto" />
                                <button v-if="comment.can_delete" type="button"
                                    class="p-1 rounded transition"
                                    :class="[dark ? 'text-zinc-500 hover:text-red-400' : 'text-zinc-400 hover:text-red-500', authed && !comment.is_mine ? '' : 'ml-auto']"
                                    :title="t('news.delete_comment')" :aria-label="t('news.delete_comment')"
                                    @click="remove(comment.id)">
                                    <Trash2 :size="12" :stroke-width="2" aria-hidden="true" />
                                </button>
                            </div>
                            <p class="text-[13px] leading-relaxed mt-1 whitespace-pre-line break-words" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ comment.body }}</p>
                        </div>
                    </div>
                </li>
            </ul>
            <div v-else class="flex flex-col items-center justify-center px-5 py-10 text-center">
                <MessageSquare :size="22" :stroke-width="1.5" aria-hidden="true" class="mb-2" :class="dark ? 'text-zinc-500' : 'text-zinc-300'" />
                <p class="text-[13px] text-zinc-500">{{ t('news.no_comments') }}</p>
            </div>

            <div v-if="hasMore" class="px-5 py-4 border-t flex flex-col items-center gap-1.5" :class="rule">
                <button type="button" :disabled="loadingMore"
                    class="text-[12px] font-semibold transition disabled:opacity-60 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                    :class="link" @click="loadMore">
                    {{ t('news.load_more_comments') }}
                </button>
                <p class="text-[11px] text-zinc-500" aria-live="polite">{{ t('news.comments_shown', { shown: visible.length, total: comments.total }) }}</p>
            </div>
        </div>
    </section>
</template>
