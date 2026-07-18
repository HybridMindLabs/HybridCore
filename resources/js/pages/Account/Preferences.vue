<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, Globe, LocateFixed } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

const props = defineProps<{
    account: { timezone: string | null; locale: string | null };
}>();

const { t, supportedLocales } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const form = useForm({
    locale: props.account.locale ?? '',
    timezone: props.account.timezone ?? '',
});

const browserZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

/**
 * The field used to be free text with "Europe/Sofia" as a placeholder, so
 * anyone who did not know IANA identifiers by heart just failed the timezone
 * validation rule. The list comes from the browser rather than the server so
 * it costs nothing in the page payload.
 *
 * A value already saved is kept in the list even if this browser does not know
 * it, otherwise opening the page would silently offer to overwrite it.
 */
const timezones = computed(() => {
    const supported = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];
    const zones = supported.length ? [...supported] : [browserZone];

    if (props.account.timezone && !zones.includes(props.account.timezone)) {
        zones.push(props.account.timezone);
    }

    return zones.sort();
});

/** Shows the effect of the choice before it is saved. */
const preview = computed(() => {
    try {
        return new Intl.DateTimeFormat(form.locale || undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: form.timezone || undefined,
        }).format(new Date());
    } catch {
        return null;
    }
});

const input = computed(() =>
    dark.value
        ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
        : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition',
);
const label = computed(() =>
    dark.value
        ? 'text-zinc-400 text-[11px] font-bold uppercase tracking-widest'
        : 'text-zinc-500 text-[11px] font-bold uppercase tracking-widest',
);
const hint = computed(() => (dark.value ? 'text-zinc-500' : 'text-zinc-500'));

function submit() {
    form.put(route('account.preferences.update'), { preserveScroll: true });
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
            <Globe :size="15" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-blue-400' : 'text-blue-600'" />
            <div>
                <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.pref_title') }}
                </h2>
                <p class="text-[12px] mt-0.5 leading-relaxed" :class="hint">{{ t('account.pref_subtitle') }}</p>
            </div>
        </div>

        <form class="p-6 flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="flex flex-col gap-2">
                    <label for="pref_locale" :class="label">{{ t('account.language') }}</label>
                    <select
                        id="pref_locale"
                        v-model="form.locale"
                        :aria-invalid="!!form.errors.locale"
                        :aria-describedby="form.errors.locale ? 'pref_locale_error' : 'pref_locale_hint'"
                        :class="[input, form.errors.locale ? '!border-red-500/60' : '']"
                    >
                        <option value="">{{ t('account.pref_language_default') }}</option>
                        <option v-for="loc in supportedLocales" :key="loc.code" :value="loc.code">{{ loc.native_name }}</option>
                    </select>
                    <p v-if="form.errors.locale" id="pref_locale_error" class="text-red-600 dark:text-red-400 text-[12px] font-semibold">
                        {{ form.errors.locale }}
                    </p>
                    <p v-else id="pref_locale_hint" class="text-[12px] leading-relaxed" :class="hint">
                        {{ t('account.pref_language_hint') }}
                    </p>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="pref_timezone" :class="label">{{ t('account.timezone') }}</label>
                    <select
                        id="pref_timezone"
                        v-model="form.timezone"
                        :aria-invalid="!!form.errors.timezone"
                        :aria-describedby="form.errors.timezone ? 'pref_timezone_error' : 'pref_timezone_hint'"
                        :class="[input, form.errors.timezone ? '!border-red-500/60' : '']"
                    >
                        <option value="">{{ t('account.pref_timezone_default') }}</option>
                        <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone.replace(/_/g, ' ') }}</option>
                    </select>

                    <button
                        v-if="browserZone && form.timezone !== browserZone"
                        type="button"
                        class="self-start inline-flex items-center gap-1.5 text-[12px] font-semibold transition rounded
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                        :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'"
                        @click="form.timezone = browserZone"
                    >
                        <LocateFixed :size="12" :stroke-width="2" />
                        {{ t('account.pref_timezone_detect') }}
                    </button>

                    <p v-if="form.errors.timezone" id="pref_timezone_error" class="text-red-600 dark:text-red-400 text-[12px] font-semibold">
                        {{ form.errors.timezone }}
                    </p>
                    <p v-else id="pref_timezone_hint" class="text-[12px] leading-relaxed" :class="hint">
                        {{ t('account.pref_timezone_hint') }}
                    </p>
                </div>
            </div>

            <p
                v-if="preview"
                class="text-[12px] rounded-xl border px-4 py-3"
                :class="dark ? 'border-zinc-800/60 bg-zinc-900/40 text-zinc-400' : 'border-zinc-200 bg-zinc-50 text-zinc-600'"
            >
                {{ t('account.pref_preview') }}
                <span class="font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-900'">{{ preview }}</span>
            </p>

            <div class="flex items-center justify-end gap-3 pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
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
                        :class="dark ? 'text-emerald-400' : 'text-emerald-700'"
                    >
                        <Check :size="13" :stroke-width="2.4" />
                        {{ t('account.pref_saved') }}
                    </span>
                </Transition>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl
                           transition disabled:opacity-60 disabled:cursor-not-allowed shadow-md shadow-blue-500/20
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                >
                    {{ t('account.save_preferences') }}
                </button>
            </div>
        </form>
    </div>
</template>
