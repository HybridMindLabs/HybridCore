<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { MessageSquare, Star, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReportButton from '@/components/UI/ReportButton.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import ServerPanel from './ServerPanel.vue';
import type { Review } from './types';

const props = defineProps<{
    serverId: number;
    reviews: { data: Review[]; total: number };
    userReview: { rating: number; body: string | null } | null;
    averageRating: number | null;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const page = usePage<{ auth: { user: unknown } | null }>();
const authed = computed(() => !!page.props.auth?.user);

const form = useForm({
    rating: props.userReview?.rating ?? 0,
    body: props.userReview?.body ?? '',
});
const hoverRating = ref(0);
const showForm = ref(false);

function submit() {
    if (!form.rating) return;
    form.post(route('servers.reviews.store', props.serverId), {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; },
    });
}

function remove(reviewId: number) {
    // A review is gone for good once deleted; ask first.
    if (!window.confirm(t('servers.delete_review_confirm'))) return;
    router.delete(route('servers.reviews.destroy', { server: props.serverId, review: reviewId }), { preserveScroll: true });
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) || 0) % colors.length];
}

const starIdle = computed(() => dark.value ? 'text-zinc-500' : 'text-zinc-300');
const link = computed(() => dark.value ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700');
const strong = computed(() => dark.value ? 'text-zinc-100' : 'text-zinc-800');
const soft = computed(() => dark.value ? 'text-zinc-400' : 'text-zinc-500');
const divider = computed(() => dark.value ? 'border-zinc-800/50' : 'border-zinc-100');
</script>

<template>
    <ServerPanel :title="t('servers.reviews')" :icon="MessageSquare">
        <template #aside>
            <div class="flex items-center gap-1.5">
                <span class="text-[11px] text-zinc-500">({{ reviews.total }})</span>
                <template v-if="averageRating">
                    <span class="flex items-center gap-0.5" role="img" :aria-label="t('servers.rate_stars', { count: Math.round(averageRating) })">
                        <Star v-for="i in 5" :key="i" :size="13" :stroke-width="1.8" aria-hidden="true"
                            :fill="i <= Math.round(averageRating) ? '#f59e0b' : 'none'"
                            :class="i <= Math.round(averageRating) ? 'text-amber-500' : starIdle" />
                    </span>
                    <span class="text-[13px] font-bold tabular-nums" :class="strong">{{ Number(averageRating).toFixed(1) }}</span>
                </template>
            </div>
        </template>

        <!-- Write / edit review -->
        <div v-if="authed" class="px-5 py-4 border-b" :class="divider">
            <button v-if="!showForm" type="button"
                class="flex items-center gap-2 text-[13px] font-semibold transition-colors" :class="link"
                @click="showForm = true">
                <Star :size="13" :stroke-width="2" aria-hidden="true" />
                {{ userReview ? t('servers.edit_review') : t('servers.write_review') }}
            </button>

            <form v-else class="flex flex-col gap-3" @submit.prevent="submit">
                <!-- Star picker -->
                <div class="flex items-center gap-1" role="radiogroup" :aria-label="t('servers.review_label')" @mouseleave="hoverRating = 0">
                    <button v-for="i in 5" :key="i" type="button" role="radio"
                        class="p-0.5 transition-transform hover:scale-110 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50"
                        :aria-checked="form.rating === i"
                        :aria-label="t('servers.rate_stars', { count: i })"
                        @mouseenter="hoverRating = i"
                        @click="form.rating = i">
                        <Star :size="20" :stroke-width="1.8" aria-hidden="true"
                            :fill="i <= (hoverRating || form.rating) ? '#f59e0b' : 'none'"
                            :class="i <= (hoverRating || form.rating) ? 'text-amber-500' : starIdle" />
                    </button>
                    <span v-if="form.rating" class="ml-1.5 text-[12px] font-semibold" :class="soft">{{ form.rating }}/5</span>
                </div>
                <p v-if="form.errors.rating" class="text-[12px] text-red-400">{{ form.errors.rating }}</p>

                <label for="review_body" class="sr-only">{{ t('servers.review_label') }}</label>
                <textarea id="review_body" v-model="form.body" rows="3" maxlength="1000"
                    :placeholder="t('servers.review_placeholder')"
                    class="w-full rounded-xl border px-4 py-2.5 text-[13px] resize-none transition focus:outline-none"
                    :class="dark
                        ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-500 focus:border-blue-500/50'
                        : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400'" />
                <p v-if="form.errors.body" class="text-[12px] text-red-400">{{ form.errors.body }}</p>

                <div class="flex items-center gap-2">
                    <button type="submit" :disabled="form.processing || !form.rating"
                        class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-[12px] font-bold transition disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ form.processing ? t('servers.saving') : (userReview ? t('servers.update_review') : t('servers.post_review')) }}
                    </button>
                    <button type="button"
                        class="px-4 py-2 rounded-lg border text-[12px] font-medium transition"
                        :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-200' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800'"
                        @click="showForm = false">{{ t('servers.cancel') }}</button>
                </div>
            </form>
        </div>
        <div v-else class="px-5 py-4 border-b" :class="divider">
            <p class="text-[13px] text-zinc-500">
                <Link :href="route('login')" class="font-semibold" :class="link">{{ t('servers.login') }}</Link>
                {{ t('servers.review_login_suffix') }}
            </p>
        </div>

        <!-- Review list -->
        <ul v-if="reviews.data.length" class="divide-y" :class="dark ? 'divide-zinc-800/50' : 'divide-zinc-100'">
            <li v-for="review in reviews.data" :key="review.id" class="px-5 py-4">
                <div class="flex items-start gap-3">
                    <component :is="review.user.username ? Link : 'div'"
                        :href="review.user.username ? route('profile.show', review.user.username) : undefined"
                        class="w-9 h-9 rounded-xl overflow-hidden shrink-0">
                        <img v-if="review.user.avatar" :src="review.user.avatar" class="w-full h-full object-cover" alt="" loading="lazy" />
                        <span v-else class="w-full h-full flex items-center justify-center text-[13px] font-black text-white uppercase"
                            :style="{ backgroundColor: avatarBg(review.user.name) }" aria-hidden="true">{{ (review.user.name || '?')[0] }}</span>
                    </component>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <component :is="review.user.username ? Link : 'span'"
                                :href="review.user.username ? route('profile.show', review.user.username) : undefined"
                                class="text-[13px] font-bold truncate"
                                :class="[strong, review.user.username ? (dark ? 'hover:text-blue-400' : 'hover:text-blue-600') : '']">{{ review.user.name }}</component>
                            <span class="flex items-center gap-0.5" role="img" :aria-label="t('servers.rate_stars', { count: review.rating })">
                                <Star v-for="i in 5" :key="i" :size="11" :stroke-width="1.8" aria-hidden="true"
                                    :fill="i <= review.rating ? '#f59e0b' : 'none'"
                                    :class="i <= review.rating ? 'text-amber-500' : starIdle" />
                            </span>
                            <span class="text-[11px] text-zinc-500">{{ review.created_at }}</span>
                            <ReportButton v-if="authed && !review.is_mine" type="review" :id="review.id" class="ml-auto" />
                            <button v-if="review.is_mine" type="button"
                                class="ml-auto p-1 rounded transition"
                                :class="dark ? 'text-zinc-500 hover:text-red-400' : 'text-zinc-400 hover:text-red-500'"
                                :aria-label="t('servers.delete_review')"
                                :title="t('servers.delete_review')"
                                @click="remove(review.id)">
                                <Trash2 :size="12" :stroke-width="2" aria-hidden="true" />
                            </button>
                        </div>
                        <p v-if="review.body" class="text-[13px] leading-relaxed mt-1 break-words" :class="soft">{{ review.body }}</p>
                    </div>
                </div>
            </li>
        </ul>
        <div v-else class="flex flex-col items-center justify-center px-5 py-10 text-center">
            <Star :size="22" :stroke-width="1.5" class="mb-2" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" aria-hidden="true" />
            <p class="text-[13px] text-zinc-500">{{ t('servers.no_reviews') }}</p>
        </div>
    </ServerPanel>
</template>
