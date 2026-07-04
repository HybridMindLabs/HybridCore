<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { BookOpen, Lock, ArrowRight, ShieldCheck, Scale } from '@lucide/vue';

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
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ app: { name: string } }>();

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta name="description" :content="seo.description" />
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
                <Breadcrumb :items="[{ label: 'Home', href: '/' }, { label: 'Rules' }]" />

                <div class="max-w-2xl">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest mb-6"
                        :class="dark ? 'border-blue-500/25 bg-blue-500/8 text-blue-400' : 'border-blue-400/30 bg-blue-50 text-blue-600'"
                    >
                        <Scale :size="11" :stroke-width="2.2" />
                        {{ rules.length }} rule{{ rules.length !== 1 ? 's' : '' }}
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        Community
                        <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">Rules</span>
                    </h1>

                    <p class="mt-4 text-[15px] leading-relaxed max-w-lg"
                       :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                        The ground rules that keep {{ page.props.app.name }} fair and fun for everyone.
                        Read them carefully before participating — not knowing them is not an excuse.
                    </p>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">

            <!-- Empty state -->
            <div v-if="rules.length === 0"
                class="rounded-2xl border p-16 text-center"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                <BookOpen :size="26" :stroke-width="1.5" class="mx-auto mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No rules published yet.</p>
            </div>

            <!-- Cards -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="(rule, index) in rules"
                    :key="rule.id"
                    :href="route('rules.show', rule.slug)"
                    class="group relative flex flex-col rounded-2xl border overflow-hidden transition-all duration-200"
                    :class="dark
                        ? 'bg-[#111113] border-zinc-800/70 hover:border-zinc-700'
                        : 'bg-white border-zinc-200 hover:border-zinc-300 hover:shadow-md'"
                >
                    <!-- Card head -->
                    <div class="flex items-center justify-between px-5 py-3.5 border-b"
                        :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                        <span class="flex items-center gap-2.5">
                            <span
                                class="text-[11px] font-black tabular-nums inline-flex w-7 h-7 rounded-lg items-center justify-center shrink-0"
                                :class="dark ? 'bg-blue-500/12 text-blue-400' : 'bg-blue-50 text-blue-600'"
                            >{{ String(index + 1).padStart(2, '0') }}</span>
                            <span
                                v-if="rule.is_system"
                                class="text-[10px] font-mono px-1.5 py-0.5 rounded flex items-center gap-1"
                                :class="dark ? 'bg-zinc-800 text-zinc-500' : 'bg-zinc-100 text-zinc-400'"
                            >
                                <Lock :size="8" /> system
                            </span>
                        </span>
                        <ArrowRight
                            :size="14" :stroke-width="2"
                            class="transition-transform group-hover:translate-x-0.5"
                            :class="dark ? 'text-zinc-600 group-hover:text-blue-400' : 'text-zinc-400 group-hover:text-blue-600'"
                        />
                    </div>

                    <!-- Body -->
                    <div class="flex flex-col flex-1 p-5">
                        <h2
                            class="text-[15px] font-black tracking-tight leading-snug transition-colors"
                            :class="dark ? 'text-zinc-100 group-hover:text-blue-400' : 'text-zinc-900 group-hover:text-blue-600'"
                        >{{ rule.title }}</h2>

                        <p
                            v-if="rule.excerpt"
                            class="text-[13px] leading-relaxed flex-1 line-clamp-3 mt-2"
                            :class="dark ? 'text-zinc-500' : 'text-zinc-500'"
                        >{{ rule.excerpt }}</p>

                        <p class="text-[11px] mt-4" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                            Updated {{ formatDate(rule.updated_at) }}
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Fair play note -->
            <div v-if="rules.length"
                class="flex items-start gap-3 rounded-2xl border px-5 py-4 mt-6"
                :class="dark ? 'border-emerald-500/15 bg-emerald-500/[0.04]' : 'border-emerald-200 bg-emerald-50'">
                <ShieldCheck :size="16" :stroke-width="1.8" class="text-emerald-500 mt-0.5 shrink-0" />
                <p class="text-[12.5px] leading-relaxed" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                    Breaking these rules can lead to warnings, mutes or bans depending on severity.
                    If you believe a punishment was unfair, contact the staff through the
                    <Link :href="route('contact.show')" class="font-semibold underline underline-offset-2"
                        :class="dark ? 'text-emerald-400 hover:text-emerald-300' : 'text-emerald-700 hover:text-emerald-800'">contact page</Link>.
                </p>
            </div>
        </div>

    </PublicLayout>
</template>
