<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import CaptchaWidget from '@/components/UI/CaptchaWidget.vue';
import { useTheme } from '@/composables/useTheme';
import { Mail, Clock, Send, CheckCircle, MessageSquare, BookOpen, ArrowRight } from '@lucide/vue';

const props = defineProps<{
    captcha: { provider: string; site_key: string | null };
}>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const page = usePage();
const success = computed(() => (page.props.flash as any)?.success);

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
    `w-full rounded-xl border px-4 py-3 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/30 ${
        dark.value
            ? 'bg-zinc-900/60 border-zinc-800 text-zinc-100 placeholder-zinc-600 focus:border-blue-500/60'
            : 'bg-white border-zinc-200 text-zinc-900 placeholder-zinc-400 focus:border-blue-400'
    }`
);
</script>

<template>
    <Head>
        <title>Contact Us</title>
        <meta name="description" content="Get in touch with our team. We'd love to hear from you." />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <div
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
        >
            <!-- Glows + dot grid (same as Home) -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]"
                    :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
                <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-14 sm:py-20">
                <Breadcrumb :items="[{ label: 'Home', href: '/' }, { label: 'Contact' }]" />

                <div class="max-w-2xl">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest mb-6"
                        :class="dark ? 'border-blue-500/25 bg-blue-500/8 text-blue-400' : 'border-blue-400/30 bg-blue-50 text-blue-600'"
                    >
                        <Clock :size="11" :stroke-width="2.2" />
                        Replies within 24 hours
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        Get in
                        <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">Touch</span>
                    </h1>

                    <p class="mt-4 text-[15px] leading-relaxed max-w-lg"
                       :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                        Have a question, suggestion, or found a problem? Fill in the form and
                        we'll get back to you as soon as possible.
                    </p>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">

                <!-- ── Form card ── -->
                <div class="rounded-2xl border overflow-hidden"
                    :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                    <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                        <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Send us a message</p>
                        <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Fields marked with * are required.</p>
                    </div>

                    <div class="p-6">
                        <!-- Success -->
                        <div
                            v-if="success"
                            class="flex items-start gap-3 rounded-xl border p-4 mb-6"
                            :class="dark ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-emerald-50 border-emerald-200 text-emerald-700'"
                        >
                            <CheckCircle :size="20" class="shrink-0 mt-0.5" />
                            <p class="text-sm font-medium">{{ success }}</p>
                        </div>

                        <form class="flex flex-col gap-5" @submit.prevent="submit">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-xs font-semibold uppercase tracking-wider" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                        Name <span class="text-red-400">*</span>
                                    </label>
                                    <input
                                        v-model="form.name"
                                        @change="form.validate('name')"
                                        type="text"
                                        :class="inputClass"
                                        placeholder="Your name"
                                        autocomplete="name"
                                    />
                                    <p v-if="form.errors.name" class="text-xs text-red-400">{{ form.errors.name }}</p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-xs font-semibold uppercase tracking-wider" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                        Email <span class="text-red-400">*</span>
                                    </label>
                                    <input
                                        v-model="form.email"
                                        @change="form.validate('email')"
                                        type="email"
                                        :class="inputClass"
                                        placeholder="you@example.com"
                                        autocomplete="email"
                                    />
                                    <p v-if="form.errors.email" class="text-xs text-red-400">{{ form.errors.email }}</p>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wider" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Subject</label>
                                <input
                                    v-model="form.subject"
                                    type="text"
                                    :class="inputClass"
                                    placeholder="What is this about?"
                                />
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wider" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                    Message <span class="text-red-400">*</span>
                                </label>
                                <textarea
                                    v-model="form.message"
                                    @change="form.validate('message')"
                                    rows="7"
                                    :class="inputClass"
                                    placeholder="Tell us how we can help…"
                                    style="resize:vertical"
                                />
                                <p v-if="form.errors.message" class="text-xs text-red-400">{{ form.errors.message }}</p>
                            </div>

                            <!-- Captcha -->
                            <CaptchaWidget
                                :provider="captcha.provider"
                                :site-key="captcha.site_key"
                                @token="onCaptchaToken"
                            />
                            <p v-if="form.errors.captcha_token" class="text-xs text-red-400">{{ form.errors.captcha_token }}</p>

                            <div>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[13px] font-bold bg-blue-500 text-white hover:bg-blue-600 transition-colors disabled:opacity-50 shadow-md shadow-blue-500/25"
                                >
                                    <Send :size="14" :stroke-width="2" />
                                    {{ form.processing ? 'Sending…' : 'Send message' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── Info sidebar ── -->
                <div class="flex flex-col gap-4">
                    <div class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <p class="px-5 py-3.5 border-b text-[11px] font-black uppercase tracking-widest"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e] text-zinc-500' : 'border-zinc-100 bg-zinc-50 text-zinc-400'"
                        >Other ways to reach us</p>

                        <div class="p-5 flex flex-col gap-5">
                            <div class="flex items-start gap-3">
                                <div
                                    class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                                    :class="dark ? 'bg-blue-500/12 text-blue-400' : 'bg-blue-50 text-blue-500'"
                                >
                                    <MessageSquare :size="15" :stroke-width="1.75" />
                                </div>
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Discord</p>
                                    <p class="text-[13px] mt-0.5" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                                        Fastest for quick questions — ask the community directly.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div
                                    class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                                    :class="dark ? 'bg-emerald-500/12 text-emerald-400' : 'bg-emerald-50 text-emerald-500'"
                                >
                                    <Mail :size="15" :stroke-width="1.75" />
                                </div>
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">This form</p>
                                    <p class="text-[13px] mt-0.5" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                                        Best for ban appeals, reports and anything private.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div
                                    class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                                    :class="dark ? 'bg-amber-500/12 text-amber-400' : 'bg-amber-50 text-amber-500'"
                                >
                                    <Clock :size="15" :stroke-width="1.75" />
                                </div>
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Response time</p>
                                    <p class="text-[13px] mt-0.5" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                                        Usually within 24 hours.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rules shortcut -->
                    <Link :href="route('rules.index')"
                        class="group flex items-center gap-3 rounded-2xl border px-5 py-4 transition-colors"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700' : 'border-zinc-200 bg-white hover:border-zinc-300 shadow-sm'">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                            :class="dark ? 'bg-violet-500/12 text-violet-400' : 'bg-violet-50 text-violet-500'">
                            <BookOpen :size="15" :stroke-width="1.75" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">Check the rules first</p>
                            <p class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Your question might already be answered.</p>
                        </div>
                        <ArrowRight :size="14" :stroke-width="2" class="shrink-0 transition-transform group-hover:translate-x-0.5"
                            :class="dark ? 'text-zinc-600 group-hover:text-blue-400' : 'text-zinc-400 group-hover:text-blue-600'" />
                    </Link>
                </div>
            </div>
        </div>

    </PublicLayout>
</template>
