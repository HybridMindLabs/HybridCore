<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';

defineProps<{ title: string; subtitle?: string }>();

const { t } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

interface AuthShell {
    games:   { name: string; slug: string }[];
    servers: { name: string; map: string; players: string; ping: string; slug: string }[];
}

const page = usePage<{ authShell?: AuthShell }>();
const shell = computed(() => page.props.authShell ?? { games: [], servers: [] });
</script>

<template>
    <PublicLayout>
        <section class="relative min-h-[calc(100vh-64px)] flex items-start px-4 py-8 sm:px-6 lg:py-12 overflow-hidden">
            <!-- Glows + dot grid (same as Home hero) -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]"
                    :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
                <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 mx-auto w-full max-w-[1100px]">
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start">

                    <!-- ── Left: marketing panel ── -->
                    <div
                        class="relative overflow-hidden rounded-2xl border p-6 lg:sticky lg:top-24 lg:p-8"
                        :class="dark
                            ? 'border-zinc-800/70 bg-[#111113]'
                            : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
                    >
                        <!-- Subtle accent glow -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full opacity-[0.06] blur-3xl bg-blue-500 pointer-events-none" />

                        <div class="relative">
                            <!-- Eyebrow badge -->
                            <div
                                class="mb-5 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest"
                                :class="dark
                                    ? 'border-blue-500/25 bg-blue-500/8 text-blue-400'
                                    : 'border-blue-400/30 bg-blue-50 text-blue-600'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse" />
                                {{ t('auth.shell.eyebrow') }}
                            </div>

                            <!-- Headline -->
                            <h2
                                class="max-w-[520px] text-[28px] font-black leading-tight tracking-tight sm:text-[34px]"
                                :class="dark ? 'text-zinc-50' : 'text-zinc-900'"
                            >{{ t('auth.shell.title') }}</h2>
                            <p
                                class="mt-3 max-w-[520px] text-[14px] leading-relaxed"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-500'"
                            >{{ t('auth.shell.subtitle') }}</p>

                            <!-- Game pills -->
                            <div class="mt-6 flex flex-wrap gap-2">
                                <div
                                    v-for="game in shell.games"
                                    :key="game.slug"
                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[12px] font-semibold"
                                    :class="dark
                                        ? 'border-zinc-800/70 bg-zinc-800/40 text-zinc-300'
                                        : 'border-zinc-200 bg-zinc-50 text-zinc-600'"
                                >
                                    <GameIcon :slug="game.slug" :alt="game.name" img-class="h-4 w-4 rounded object-cover" />
                                    {{ game.name }}
                                </div>
                            </div>

                            <!-- Live server preview -->
                            <div
                                class="mt-7 rounded-xl border p-4"
                                :class="dark
                                    ? 'border-zinc-800/60 bg-[#1a1a1e]'
                                    : 'border-zinc-100 bg-zinc-50'"
                            >
                                <div class="mb-4 flex items-center justify-between">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wider" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                            {{ t('auth.shell.preview_title') }}
                                        </p>
                                        <p class="mt-0.5 text-[13px] font-bold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">
                                            {{ t('auth.shell.online_now') }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-bold"
                                        :class="dark ? 'border-zinc-800/70 bg-zinc-800/50 text-zinc-300' : 'border-zinc-200 bg-white text-zinc-700'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                        {{ t('auth.shell.online_now') }}
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <div
                                        v-for="server in shell.servers"
                                        :key="server.name"
                                        class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 rounded-xl border px-3 py-2.5"
                                        :class="dark
                                            ? 'border-zinc-800/50 bg-[#111113]'
                                            : 'border-zinc-200 bg-white'"
                                    >
                                        <GameIcon :slug="server.slug" :alt="server.name" img-class="h-9 w-9 rounded-lg object-cover shrink-0" />
                                        <div class="min-w-0">
                                            <p class="truncate text-[13px] font-semibold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">
                                                {{ server.name }}
                                            </p>
                                            <p class="mt-0.5 truncate text-[11px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                                {{ server.map }}
                                            </p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-[12px] font-semibold" :class="dark ? 'text-zinc-200' : 'text-zinc-700'">{{ server.players }}</p>
                                            <p class="mt-0.5 text-[11px] font-semibold text-blue-500">{{ server.ping }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Right: form card ── -->
                    <div
                        class="rounded-2xl border p-5 sm:p-6"
                        :class="dark
                            ? 'border-zinc-800/70 bg-[#111113]'
                            : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
                    >
                        <!-- Accent bar -->
                        <div class="mb-5 h-1 w-12 rounded-full bg-gradient-to-r from-blue-500 to-violet-500" />

                        <h1
                            class="text-[24px] font-black tracking-tight"
                            :class="dark ? 'text-zinc-50' : 'text-zinc-900'"
                        >{{ title }}</h1>
                        <p
                            v-if="subtitle"
                            class="mt-2 text-[13px] leading-relaxed"
                            :class="dark ? 'text-zinc-400' : 'text-zinc-500'"
                        >{{ subtitle }}</p>

                        <div class="mt-6">
                            <slot />
                        </div>

                        <div
                            v-if="$slots.footer"
                            class="mt-6 border-t pt-5 text-center text-[13px]"
                            :class="dark ? 'border-zinc-800/60 text-zinc-500' : 'border-zinc-100 text-zinc-400'"
                        >
                            <slot name="footer" />
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </PublicLayout>
</template>
