<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Check, MailX } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{
    category: string;
    username: string;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const done = ref(false);
const working = ref(false);

/**
 * The page printed the raw slug from the URL — "digest" — at the reader. These
 * are the same two categories the Email Preferences page names, so it reuses
 * those labels and falls back to the slug for anything unexpected.
 */
const categoryLabel = computed(() => {
    const key = `account.email_cat_${props.category}`;
    const label = t(key);

    return label === key ? props.category : label;
});

function confirm() {
    working.value = true;

    router.delete(route('unsubscribe.destroy', props.category), {
        onSuccess: () => {
            done.value = true;
        },
        onFinish: () => {
            working.value = false;
        },
    });
}
</script>

<template>
    <Head>
        <title>{{ t('account.unsub_title') }}</title>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout>
        <div class="min-h-[60vh] flex items-center justify-center px-4 py-16">
            <div class="max-w-md w-full text-center">
                <!-- These colours were fixed dark, so on the light theme the
                     whole page was near-white text on white. -->
                <div
                    class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-5"
                    :class="done
                        ? dark ? 'bg-emerald-500/10' : 'bg-emerald-50'
                        : dark ? 'bg-cyan-500/10' : 'bg-cyan-50'"
                >
                    <component
                        :is="done ? Check : MailX"
                        :size="24"
                        :stroke-width="1.5"
                        aria-hidden="true"
                        :class="done
                            ? dark ? 'text-emerald-400' : 'text-emerald-700'
                            : dark ? 'text-cyan-400' : 'text-cyan-600'"
                    />
                </div>

                <template v-if="!done">
                    <h1 class="text-2xl font-black mb-2" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('account.unsub_title') }}
                    </h1>
                    <p class="mb-1" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                        {{ t('account.unsub_greeting', { name: username }) }}
                    </p>
                    <p class="mb-6 leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                        {{ t('account.unsub_body', { category: categoryLabel }) }}
                    </p>

                    <button
                        type="button"
                        :disabled="working"
                        class="border font-semibold px-6 py-2.5 rounded-xl transition disabled:opacity-60
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                        :class="dark
                            ? 'bg-red-500/15 border-red-500/30 text-red-300 hover:bg-red-500/25'
                            : 'bg-red-50 border-red-300 text-red-700 hover:bg-red-100'"
                        @click="confirm"
                    >
                        {{ t('account.unsub_button') }}
                    </button>
                </template>

                <template v-else>
                    <h1 class="text-2xl font-black mb-2" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('account.unsub_done_title') }}
                    </h1>
                    <p class="mb-6" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                        {{ t('account.unsub_done_body', { category: categoryLabel }) }}
                    </p>

                    <div class="flex flex-col items-center gap-2 text-[13px]">
                        <!-- Offers the way back to the rest of the settings,
                             which the dead end here never did. -->
                        <Link
                            :href="route('account.index')"
                            class="font-semibold transition"
                            :class="dark ? 'text-cyan-400 hover:text-cyan-300' : 'text-cyan-700 hover:text-cyan-800'"
                        >{{ t('account.unsub_manage') }}</Link>
                        <Link
                            :href="route('home')"
                            class="transition"
                            :class="dark ? 'text-zinc-500 hover:text-zinc-300' : 'text-zinc-500 hover:text-zinc-800'"
                        >{{ t('account.unsub_back') }}</Link>
                    </div>
                </template>
            </div>
        </div>
    </PublicLayout>
</template>
