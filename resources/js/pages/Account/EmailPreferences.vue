<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, MailCheck, ShieldCheck } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

const props = defineProps<{
    preferences: Record<string, boolean> | string[];
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

/**
 * Only categories something actually consults before sending. System
 * notifications and announcements used to sit here too, but no sender ever
 * read them — the switches moved nothing.
 */
const categories = computed(() => [
    { key: 'email_messages', label: t('account.email_cat_messages'), desc: t('account.email_cat_messages_desc') },
    { key: 'email_digest', label: t('account.email_cat_digest'), desc: t('account.email_cat_digest_desc') },
]);

/**
 * The column briefly held a second, list-shaped format where the presence of
 * a name meant opted *out*. Rows written back then still exist, so translate
 * them rather than silently resubscribing anyone.
 */
const LEGACY_OPT_OUT: Record<string, string> = { email_messages: 'dm_email' };

const initial = computed<Record<string, boolean>>(() => {
    const source = props.preferences ?? {};

    if (Array.isArray(source)) {
        return Object.fromEntries(
            categories.value.map((c) => [c.key, !source.includes(LEGACY_OPT_OUT[c.key] ?? c.key)]),
        );
    }

    // Absent means opted in — matching the `?? true` defaults on the server.
    return Object.fromEntries(categories.value.map((c) => [c.key, source[c.key] !== false]));
});

const form = useForm<Record<string, boolean>>({ ...initial.value });

function submit() {
    form.put(route('account.email-preferences.update'), { preserveScroll: true });
}
</script>

<template>
    <div
        class="rounded-2xl border overflow-hidden"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
    >
        <div
            class="px-6 py-4 border-b flex items-start gap-2.5"
            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'"
        >
            <MailCheck :size="15" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-cyan-400' : 'text-cyan-600'" />
            <div>
                <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.email_title') }}
                </h2>
                <p class="text-[12px] mt-0.5 leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.email_subtitle') }}
                </p>
            </div>
        </div>

        <form class="p-6 flex flex-col gap-1" @submit.prevent="submit">
            <div
                v-for="cat in categories"
                :key="cat.key"
                class="flex items-start justify-between gap-5 py-4 border-b last:border-b-0"
                :class="dark ? 'border-zinc-800/40' : 'border-zinc-100'"
            >
                <div class="min-w-0">
                    <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ cat.label }}</p>
                    <p class="text-[12px] mt-1 leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ cat.desc }}
                    </p>
                </div>

                <button
                    type="button"
                    role="switch"
                    :aria-checked="form[cat.key]"
                    :aria-label="cat.label"
                    class="relative w-10 h-5 rounded-full shrink-0 mt-0.5 transition-colors cursor-pointer
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-500"
                    :class="form[cat.key] ? 'bg-cyan-600' : dark ? 'bg-zinc-700' : 'bg-zinc-300'"
                    @click="form[cat.key] = !form[cat.key]"
                >
                    <span
                        class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform"
                        :class="form[cat.key] ? 'translate-x-5' : 'translate-x-0'"
                    />
                </button>
            </div>

            <div
                class="mt-4 flex items-start gap-2.5 rounded-xl border px-4 py-3"
                :class="dark ? 'border-zinc-800/60 bg-zinc-900/40' : 'border-zinc-200 bg-zinc-50'"
            >
                <ShieldCheck :size="14" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-zinc-400' : 'text-zinc-500'" />
                <div>
                    <p class="text-[12px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                        {{ t('account.email_note_title') }}
                    </p>
                    <p class="text-[12px] mt-0.5 leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ t('account.email_note_body') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-5">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl
                           transition disabled:opacity-60 disabled:cursor-not-allowed
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-500"
                >
                    {{ t('account.email_save') }}
                </button>
                <Transition
                    enter-active-class="transition duration-200"
                    enter-from-class="opacity-0 translate-y-1"
                    leave-active-class="transition duration-200"
                    leave-to-class="opacity-0"
                >
                    <span
                        v-if="form.recentlySuccessful"
                        role="status"
                        class="inline-flex items-center gap-1 text-[12px] font-semibold"
                        :class="dark ? 'text-emerald-400' : 'text-emerald-600'"
                    >
                        <Check :size="13" :stroke-width="2.4" />
                        {{ t('account.email_saved') }}
                    </span>
                </Transition>
            </div>
        </form>
    </div>
</template>
