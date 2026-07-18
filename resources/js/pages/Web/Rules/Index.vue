<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { BookOpen, ArrowRight, ShieldCheck, Scale, Clock } from '@lucide/vue';

interface Rule {
    id: number;
    slug: string;
    title: string;
    excerpt: string | null;
    is_system: boolean;
    updated_at: string;
}

interface Seo {
    title: string;
    description: string;
}

const props = defineProps<{ rules: Rule[]; seo: Seo }>();

const { theme } = useTheme();
const { t, currentLocale } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ app: { name: string } }>();

/** Was pinned to 'en-GB', so every visitor saw British dates. */
function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(currentLocale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

const countLabel = computed(() => props.rules.length === 1
    ? t('rules.rules_count_one')
    : t('rules.rules_count_many', { count: props.rules.length }));

/** Most recent change across all rules — "have these moved lately?". */
const lastUpdated = computed(() => {
    const stamps = props.rules
        .map(rule => new Date(rule.updated_at).getTime())
        .filter(time => !Number.isNaN(time));

    return stamps.length ? new Date(Math.max(...stamps)) : null;
});

const heroStats = computed(() => [
    {
        icon: BookOpen,
        value: String(props.rules.length),
        label: t('rules.stat_rules'),
        hint: t('rules.stat_rules_hint'),
    },
    {
        icon: Clock,
        value: lastUpdated.value
            ? lastUpdated.value.toLocaleDateString(currentLocale.value, { day: 'numeric', month: 'short' })
            : '—',
        label: t('rules.stat_updated'),
        hint: t('rules.stat_updated_hint'),
    },
]);
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta name="description" :content="seo.description" />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <section
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
            :aria-label="t('rules.title')"
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
                    { label: t('rules.title') },
                ]" />

                <div class="grid gap-8 lg:gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,340px)] lg:items-end mt-4">

                    <div class="max-w-xl">
                        <div
                            class="hc-hero-in inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                            :class="dark ? 'border-blue-500/25 bg-blue-500/10 text-blue-400' : 'border-blue-500/30 bg-blue-500/10 text-blue-700'"
                        >
                            <Scale :size="11" :stroke-width="2.2" aria-hidden="true" />
                            {{ countLabel }}
                        </div>

                        <h1 class="hc-hero-in hc-hero-in--1 mt-4 text-[30px] sm:text-[40px] font-black tracking-tight leading-[1.07]"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ t('rules.heading_1') }}
                            <span class="hc-hero-gradient bg-clip-text text-transparent">{{ t('rules.heading_2') }}</span>
                        </h1>

                        <p class="hc-hero-in hc-hero-in--2 mt-3 text-[15px] leading-relaxed max-w-lg"
                           :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                            {{ t('rules.hero_description', { app: page.props.app.name }) }}
                        </p>

                        <div v-if="rules.length" class="hc-hero-in hc-hero-in--3 flex items-center gap-2.5 mt-6 flex-wrap">
                            <Link :href="route('rules.show', rules[0].slug)"
                                class="group inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-[13.5px] px-5 py-2.5 rounded-xl transition shadow-md shadow-blue-600/25 hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                {{ t('rules.start_reading') }}
                                <ArrowRight :size="14" :stroke-width="2.2"
                                    class="transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                            </Link>
                            <Link :href="route('contact.show')"
                                class="inline-flex items-center font-bold text-[13.5px] px-5 py-2.5 rounded-xl border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-300 text-zinc-700 hover:border-zinc-400 hover:bg-white'">
                                {{ t('rules.contact_staff') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Figures a reader actually wonders about: how much is there
                         to read, and has anything changed recently. -->
                    <dl v-if="rules.length" class="hc-hero-in hc-hero-in--2 grid grid-cols-2 gap-2.5">
                        <div v-for="(item, i) in heroStats" :key="item.label"
                            class="hc-reveal rounded-xl border px-3 py-2.5 backdrop-blur-md"
                            :style="{ animationDelay: 0.18 + i * 0.06 + 's' }"
                            :class="dark
                                ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                                : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'"
                            :title="item.hint">
                            <component :is="item.icon" :size="13" :stroke-width="1.9"
                                class="text-blue-500 mb-1.5" aria-hidden="true" />
                            <dd class="text-[17px] font-black leading-none tabular-nums"
                                :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ item.value }}</dd>
                            <dt class="text-[10px] font-bold uppercase tracking-widest mt-1.5"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ item.label }}</dt>
                            <p class="text-[10.5px] leading-snug mt-1"
                               :class="dark ? 'text-zinc-600' : 'text-zinc-500'">{{ item.hint }}</p>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">

            <!-- Empty state -->
            <div v-if="rules.length === 0"
                class="flex flex-col items-center text-center rounded-2xl border px-6 py-16"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                    :class="dark ? 'bg-zinc-900 text-zinc-700' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <BookOpen :size="26" :stroke-width="1.4" />
                </span>
                <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                    {{ t('rules.empty_title') }}
                </p>
                <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('rules.empty_hint') }}
                </p>
            </div>

            <template v-else>
                <div class="max-w-5xl mx-auto">
                    <div class="flex items-end justify-between gap-4 mb-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-1 h-9 rounded-full bg-blue-500 shrink-0 mt-0.5" aria-hidden="true" />
                            <div class="min-w-0">
                                <h2 class="text-[19px] font-black tracking-tight"
                                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('rules.title') }}</h2>
                                <p class="text-[12.5px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                    {{ t('rules.browse_hint') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Two columns, numbered left to right. Still an <ol>, so a
                         screen reader announces the ordering the numbers imply. -->
                    <ol class="grid sm:grid-cols-2 gap-4">
                        <li v-for="(rule, index) in rules" :key="rule.id"
                            class="hc-reveal"
                            :style="{ animationDelay: Math.min(index, 11) * 0.045 + 's' }">
                            <Link
                                :href="route('rules.show', rule.slug)"
                                class="hc-rule-card group relative flex flex-col h-full rounded-2xl border overflow-hidden px-5 py-5 transition-all duration-200 hover:-translate-y-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="dark
                                    ? 'border-zinc-800/70 bg-[#111113] hover:border-blue-500/40 hover:shadow-xl hover:shadow-black/40'
                                    : 'border-zinc-200 bg-white hover:border-blue-400/50 shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-[0_10px_28px_rgba(0,0,0,0.10)]'"
                                :aria-label="t('rules.read_rule', { title: rule.title })"
                            >
                                <!-- Oversized numeral as texture, behind the text -->
                                <span class="pointer-events-none absolute -top-3 right-2 text-[76px] font-black leading-none tabular-nums select-none transition-opacity"
                                    :class="dark ? 'text-white/[0.035] group-hover:text-blue-400/[0.08]' : 'text-zinc-900/[0.04] group-hover:text-blue-600/[0.07]'"
                                    aria-hidden="true">{{ index + 1 }}</span>

                                <span class="relative flex items-center gap-2.5">
                                    <span
                                        class="text-[12.5px] font-black tabular-nums inline-flex w-8 h-8 rounded-xl items-center justify-center shrink-0 transition-colors"
                                        :class="dark
                                            ? 'bg-blue-500/15 text-blue-400 group-hover:bg-blue-500/25'
                                            : 'bg-blue-500/10 text-blue-700 group-hover:bg-blue-500/20'"
                                        aria-hidden="true"
                                    >{{ index + 1 }}</span>
                                    <span class="h-px flex-1 transition-colors"
                                        :class="dark ? 'bg-zinc-800 group-hover:bg-blue-500/25' : 'bg-zinc-200 group-hover:bg-blue-500/25'"
                                        aria-hidden="true" />
                                </span>

                                <h3 class="relative text-[16px] font-black tracking-tight leading-snug mt-3.5 transition-colors"
                                    :class="dark ? 'text-zinc-100 group-hover:text-blue-400' : 'text-zinc-900 group-hover:text-blue-700'"
                                >{{ rule.title }}</h3>

                                <p v-if="rule.excerpt"
                                    class="relative text-[13px] leading-relaxed mt-2 line-clamp-3 flex-1"
                                    :class="dark ? 'text-zinc-400' : 'text-zinc-600'"
                                >{{ rule.excerpt }}</p>

                                <span class="relative flex items-center justify-between gap-3 mt-4 pt-3 border-t"
                                    :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'">
                                    <span class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-500'">
                                        {{ t('rules.updated', { date: formatDate(rule.updated_at) }) }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-[12px] font-bold transition-colors"
                                        :class="dark ? 'text-zinc-500 group-hover:text-blue-400' : 'text-zinc-600 group-hover:text-blue-700'">
                                        {{ t('rules.read_more') }}
                                        <ArrowRight :size="13" :stroke-width="2.2" aria-hidden="true"
                                            class="transition-transform group-hover:translate-x-1" />
                                    </span>
                                </span>
                            </Link>
                        </li>
                    </ol>

                    <!-- What happens if you break them — the question the list
                         raises and never answered. -->
                    <div class="flex items-start gap-3.5 rounded-2xl border px-5 py-4 mt-4"
                        :class="dark ? 'border-emerald-500/15 bg-emerald-500/[0.05]' : 'border-emerald-300/70 bg-emerald-500/[0.06]'">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                            :class="dark ? 'bg-emerald-500/15' : 'bg-emerald-500/15'" aria-hidden="true">
                            <ShieldCheck :size="17" :stroke-width="1.9" class="text-emerald-600 dark:text-emerald-400" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-900'">
                                {{ t('rules.enforcement_title') }}
                            </p>
                            <p class="text-[12.5px] leading-relaxed mt-1" :class="dark ? 'text-zinc-400' : 'text-zinc-700'">
                                {{ t('rules.enforcement_body') }}
                                {{ t('rules.enforcement_appeal') }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

    </PublicLayout>
</template>
