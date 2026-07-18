<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import CaptchaWidget from '@/components/UI/CaptchaWidget.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { Mail, Clock, Send, CheckCircle, MessageSquare, BookOpen, ArrowRight } from '@lucide/vue';

const props = defineProps<{
    captcha: { provider: string; site_key: string | null };
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage();
const success = computed(() => (page.props.flash as any)?.success);

const includeItems = computed(() => [
    t('contact.include_name'),
    t('contact.include_server'),
    t('contact.include_what'),
]);

const channels = computed(() => [
    {
        icon: MessageSquare,
        label: t('contact.channel_discord'),
        hint: t('contact.channel_discord_hint'),
        tint: dark.value ? 'bg-blue-500/15 text-blue-400' : 'bg-blue-500/10 text-blue-700',
    },
    {
        icon: Mail,
        label: t('contact.channel_form'),
        hint: t('contact.channel_form_hint'),
        tint: dark.value ? 'bg-emerald-500/15 text-emerald-400' : 'bg-emerald-500/10 text-emerald-700',
    },
    {
        icon: Clock,
        label: t('contact.channel_response'),
        hint: t('contact.channel_response_hint'),
        tint: dark.value ? 'bg-amber-500/15 text-amber-400' : 'bg-amber-500/10 text-amber-700',
    },
]);

const form = useForm({
    name:          '',
    email:         '',
    subject:       '',
    message:       '',
    captcha_token: '',
}).withPrecognition('post', route('contact.store'));

function onCaptchaToken(token: string) {
    form.captcha_token = token;
}

function submit() {
    form.submit({
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const inputClass = computed(() =>
    `w-full rounded-xl border px-4 py-3 text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40 ${
        dark.value
            ? 'bg-zinc-900/60 border-zinc-800 text-zinc-100 placeholder-zinc-600 focus:border-blue-500/60'
            : 'bg-white border-zinc-300 text-zinc-900 placeholder-zinc-500 focus:border-blue-500/60'
    }`
);

const labelClass = computed(() =>
    `text-[11px] font-bold uppercase tracking-wider ${dark.value ? 'text-zinc-400' : 'text-zinc-600'}`
);
</script>

<template>
    <Head>
        <title>{{ t('contact.seo_title') }}</title>
        <meta name="description" :content="t('contact.seo_description')" />
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="t('contact.seo_title')" />
        <meta property="og:description" :content="t('contact.seo_description')" />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <section
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
            :aria-label="t('contact.title')"
        >
            <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                <div class="hc-hero-glow absolute -top-32 left-1/4 w-[520px] h-[380px] rounded-full blur-[120px]"
                    :class="dark ? 'bg-blue-500/8' : 'bg-blue-400/10'" />
                <div class="hc-hero-glow hc-hero-glow--slow absolute -top-16 right-1/4 w-[320px] h-[300px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/6' : 'bg-violet-400/8'" />
                <div class="absolute inset-0" :class="dark ? 'opacity-50' : 'opacity-[0.35]'"
                    :style="`background-image:radial-gradient(circle,${dark ? 'rgba(255,255,255,0.035)' : 'rgba(24,24,27,0.05)'} 1px,transparent 1px);background-size:28px 28px`" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-8 sm:py-12">
                <Breadcrumb :items="[
                    { label: t('navigation.nav_home'), href: route('home') },
                    { label: t('contact.title') },
                ]" />

                <div class="grid gap-8 lg:gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,340px)] lg:items-end mt-4">

                    <div class="max-w-xl">
                        <div
                            class="hc-hero-in inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                            :class="dark ? 'border-blue-500/25 bg-blue-500/10 text-blue-400' : 'border-blue-500/30 bg-blue-500/10 text-blue-700'"
                        >
                            <Clock :size="11" :stroke-width="2.2" aria-hidden="true" />
                            {{ t('contact.response_badge') }}
                        </div>

                        <h1 class="hc-hero-in hc-hero-in--1 mt-4 text-[30px] sm:text-[40px] font-black tracking-tight leading-[1.07]"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ t('contact.heading_1') }}
                            <span class="hc-hero-gradient bg-clip-text text-transparent">{{ t('contact.heading_2') }}</span>
                        </h1>

                        <p class="hc-hero-in hc-hero-in--2 mt-3 text-[15px] leading-relaxed max-w-lg"
                           :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                            {{ t('contact.hero_description') }}
                        </p>
                    </div>

                    <!-- What to put in the message. Most back-and-forth on a
                         gaming contact form is the staff asking for these three
                         things, so ask up front. -->
                    <div class="hc-hero-in hc-hero-in--2 rounded-2xl border px-5 py-4 backdrop-blur-md"
                        :class="dark
                            ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                            : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'">
                        <p class="text-[12.5px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ t('contact.include_title') }}
                        </p>
                        <p class="text-[11.5px] leading-snug mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                            {{ t('contact.include_hint') }}
                        </p>

                        <ul class="flex flex-col gap-2 mt-3.5">
                            <li v-for="(item, i) in includeItems" :key="item"
                                class="hc-reveal flex items-start gap-2.5 text-[12.5px]"
                                :style="{ animationDelay: 0.2 + i * 0.06 + 's' }"
                                :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                                <CheckCircle :size="14" :stroke-width="2" aria-hidden="true"
                                    class="shrink-0 mt-px text-emerald-500" />
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">

                <!-- ── Form card ── -->
                <div class="rounded-2xl border overflow-hidden"
                    :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                    <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                        <h2 class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('contact.form_title') }}</h2>
                        <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-600'">{{ t('contact.form_required_note') }}</p>
                    </div>

                    <div class="p-6">
                        <!-- role="status" so the confirmation is announced, not
                             just shown. -->
                        <div
                            v-if="success"
                            role="status"
                            class="flex items-start gap-3 rounded-xl border p-4 mb-6"
                            :class="dark ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-800'"
                        >
                            <CheckCircle :size="20" class="shrink-0 mt-0.5" aria-hidden="true" />
                            <div>
                                <p class="text-[13px] font-bold">{{ t('contact.success_title') }}</p>
                                <p class="text-[13px] mt-0.5">{{ success }}</p>
                            </div>
                        </div>

                        <!-- Every label carries a `for`. Without it, clicking a
                             label did nothing and screen readers announced the
                             fields unnamed. -->
                        <form class="flex flex-col gap-5" @submit.prevent="submit">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label for="contact-name" :class="labelClass">
                                        {{ t('contact.field_name') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                                    </label>
                                    <input
                                        id="contact-name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        :class="inputClass"
                                        :placeholder="t('contact.field_name')"
                                        autocomplete="name"
                                        :aria-invalid="form.errors.name ? 'true' : undefined"
                                        :aria-describedby="form.errors.name ? 'contact-name-error' : undefined"
                                        @change="form.validate('name')"
                                    />
                                    <p v-if="form.errors.name" id="contact-name-error" role="alert"
                                       class="text-[12px] text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label for="contact-email" :class="labelClass">
                                        {{ t('contact.field_email') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                                    </label>
                                    <input
                                        id="contact-email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        :class="inputClass"
                                        placeholder="you@example.com"
                                        autocomplete="email"
                                        :aria-invalid="form.errors.email ? 'true' : undefined"
                                        :aria-describedby="form.errors.email ? 'contact-email-error' : 'contact-email-hint'"
                                        @change="form.validate('email')"
                                    />
                                    <p v-if="form.errors.email" id="contact-email-error" role="alert"
                                       class="text-[12px] text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                                    <p v-else id="contact-email-hint" class="text-[11.5px]"
                                       :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('contact.field_email_hint') }}</p>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="contact-subject" :class="labelClass">{{ t('contact.field_subject') }}</label>
                                <input
                                    id="contact-subject"
                                    v-model="form.subject"
                                    type="text"
                                    :class="inputClass"
                                    :placeholder="t('contact.field_subject')"
                                />
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="contact-message" :class="labelClass">
                                    {{ t('contact.field_message') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
                                </label>
                                <textarea
                                    id="contact-message"
                                    v-model="form.message"
                                    rows="7"
                                    required
                                    :class="inputClass"
                                    :placeholder="t('contact.field_message')"
                                    style="resize:vertical"
                                    :aria-invalid="form.errors.message ? 'true' : undefined"
                                    :aria-describedby="form.errors.message ? 'contact-message-error' : 'contact-message-hint'"
                                    @change="form.validate('message')"
                                />
                                <p v-if="form.errors.message" id="contact-message-error" role="alert"
                                   class="text-[12px] text-red-600 dark:text-red-400">{{ form.errors.message }}</p>
                                <p v-else id="contact-message-hint" class="text-[11.5px]"
                                   :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('contact.field_message_hint') }}</p>
                            </div>

                            <!-- Captcha -->
                            <CaptchaWidget
                                :provider="captcha.provider"
                                :site-key="captcha.site_key"
                                @token="onCaptchaToken"
                            />
                            <p v-if="form.errors.captcha_token" role="alert"
                               class="text-[12px] text-red-600 dark:text-red-400">{{ form.errors.captcha_token }}</p>

                            <div>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[13.5px] font-bold bg-blue-600 text-white hover:bg-blue-500 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-md shadow-blue-600/25 hover:-translate-y-0.5 disabled:hover:translate-y-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 focus-visible:ring-offset-2"
                                    :class="dark ? 'focus-visible:ring-offset-[#111113]' : 'focus-visible:ring-offset-white'"
                                >
                                    <Send :size="14" :stroke-width="2" aria-hidden="true"
                                        :class="form.processing ? 'animate-pulse' : ''" />
                                    {{ form.processing ? t('contact.submitting') : t('contact.submit') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── Info sidebar ── -->
                <aside class="flex flex-col gap-4" :aria-label="t('contact.channels_title')">
                    <div class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                        <h2 class="px-5 py-3.5 border-b text-[11px] font-black uppercase tracking-widest"
                            :class="dark ? 'border-zinc-800/60 bg-[#17171a] text-zinc-400' : 'border-zinc-200 bg-zinc-50 text-zinc-600'"
                        >{{ t('contact.channels_title') }}</h2>

                        <dl class="p-5 flex flex-col gap-5">
                            <div v-for="channel in channels" :key="channel.label" class="flex items-start gap-3">
                                <div
                                    class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                                    :class="channel.tint"
                                    aria-hidden="true"
                                >
                                    <component :is="channel.icon" :size="15" :stroke-width="1.9" />
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[11px] font-bold uppercase tracking-widest"
                                        :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ channel.label }}</dt>
                                    <dd class="text-[13px] leading-relaxed mt-0.5"
                                        :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ channel.hint }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>

                    <!-- Rules shortcut -->
                    <Link :href="route('rules.index')"
                        class="group flex items-center gap-3 rounded-2xl border px-5 py-4 transition-all hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-blue-500/40' : 'border-zinc-200 bg-white hover:border-blue-400/50 shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-md'">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                            :class="dark ? 'bg-violet-500/15 text-violet-400' : 'bg-violet-500/10 text-violet-700'"
                            aria-hidden="true">
                            <BookOpen :size="15" :stroke-width="1.9" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-bold transition-colors"
                                :class="dark ? 'text-zinc-200 group-hover:text-blue-400' : 'text-zinc-900 group-hover:text-blue-700'">
                                {{ t('contact.rules_shortcut_title') }}
                            </p>
                            <p class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                                {{ t('contact.rules_shortcut_hint') }}
                            </p>
                        </div>
                        <ArrowRight :size="15" :stroke-width="2" aria-hidden="true"
                            class="shrink-0 transition-transform group-hover:translate-x-1"
                            :class="dark ? 'text-zinc-600 group-hover:text-blue-400' : 'text-zinc-400 group-hover:text-blue-600'" />
                    </Link>
                </aside>
            </div>
        </div>

    </PublicLayout>
</template>
