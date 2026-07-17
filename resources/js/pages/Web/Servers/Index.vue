<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Gamepad2, Users, Server, Wifi, Search } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';

interface Game {
    id: number; name: string; slug: string; icon: string; color: string;
    cover_url: string | null;
    servers_count: number; players_online: number; online_servers: number;
}

const props = defineProps<{
    games: Game[];
    totals: { games: number; servers: number; players: number; online_servers: number };
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const search = ref('');
const filtered = computed(() => {
    if (!search.value.trim()) return props.games;
    const q = search.value.toLowerCase();
    return props.games.filter(g => g.name.toLowerCase().includes(q));
});

function onlinePercent(g: Game) {
    if (!g.servers_count) return 0;
    return Math.round((g.online_servers / g.servers_count) * 100);
}
</script>

<template>
    <Head :title="t('servers.title')" />

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <div class="relative overflow-hidden border-b border-zinc-800/60">
            <!-- Mosaic background — images stay! -->
            <div class="absolute inset-0 grid grid-cols-4">
                <div
                    v-for="game in games.slice(0, 8)"
                    :key="game.id"
                    class="bg-cover bg-center"
                    :style="game.cover_url
                        ? { backgroundImage: `url(${game.cover_url})` }
                        : { backgroundColor: game.color }"
                />
            </div>
            <!-- Overlay — always dark so text stays readable -->
            <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(9,9,11,0.78) 0%, rgba(9,9,11,0.94) 60%, rgba(9,9,11,1) 100%)" />

            <!-- Glows + dot grid (same as Home) -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px] bg-blue-500/6" />
                <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px] bg-violet-500/5" />
                <div class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-14 sm:py-20">
                <Breadcrumb :items="[{ label: 'Home', href: route('home') }, { label: t('servers.title') }]" />

                <div class="max-w-2xl">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/8 text-emerald-400 text-[11px] font-bold uppercase tracking-widest mb-6">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                        {{ t('servers.hero_badge') }}
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black text-zinc-100 leading-[1.05] tracking-tight">
                        {{ t('servers.hero_title_1') }}
                        <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">{{ t('servers.hero_title_2') }}</span>
                    </h1>
                    <p class="mt-4 text-[15px] text-zinc-400 leading-relaxed max-w-lg">
                        {{ t('servers.hero_subtitle').replace(':count', String(totals.games)) }}
                    </p>

                    <!-- Stats pills (Home style) -->
                    <div class="flex items-center gap-5 mt-8 flex-wrap">
                        <div v-for="item in [
                            { icon: Gamepad2, value: totals.games,          label: t('servers.games')         },
                            { icon: Server,   value: totals.servers,        label: t('servers.servers_label') },
                            { icon: Wifi,     value: totals.online_servers, label: t('servers.online_now')    },
                            { icon: Users,    value: totals.players,        label: t('servers.players_label') },
                        ]" :key="item.label" class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 bg-zinc-900 border border-zinc-800">
                                <component :is="item.icon" :size="14" :stroke-width="1.8" class="text-blue-400" />
                            </div>
                            <div>
                                <p class="text-[16px] font-black leading-none tabular-nums text-zinc-100">{{ item.value.toLocaleString() }}</p>
                                <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-zinc-600">{{ item.label }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="relative w-full max-w-sm mt-8">
                        <Search :size="15" :stroke-width="1.8" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-500" />
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="t('servers.search_game')"
                            class="w-full pl-10 pr-4 py-3 rounded-xl bg-white/6 border border-white/10 text-white text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 transition"
                        />
                    </div>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <!-- ── Games grid ── -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">

            <div class="flex items-center justify-between mb-6">
                <h2 class="text-[17px] font-bold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">
                    {{ t('servers.browse_by_game') }}
                    <span class="ml-2 text-[13px] font-normal" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                        {{ filtered.length }} {{ t('servers.games').toLowerCase() }}
                    </span>
                </h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link
                    v-for="game in filtered"
                    :key="game.id"
                    :href="route('servers.game', game.slug)"
                    class="group rounded-xl border overflow-hidden block transition-all duration-200 hover:-translate-y-0.5"
                    :class="dark
                        ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/70 hover:shadow-xl hover:shadow-black/40'
                        : 'border-zinc-200 bg-zinc-50 shadow-[0_1px_4px_rgba(0,0,0,0.07)] hover:shadow-md hover:shadow-zinc-300/40'"
                >
                    <!-- Banner image — KEEP! -->
                    <div class="relative h-[112px] overflow-hidden">
                        <div
                            v-if="game.cover_url"
                            class="absolute inset-[-6px] bg-cover bg-center transition-transform duration-500 group-hover:scale-105"
                            :style="{
                                backgroundImage: `url(${game.cover_url})`,
                                filter: dark ? 'blur(2px) brightness(0.5)' : 'blur(2px) brightness(0.45)',
                            }"
                        />
                        <div
                            v-else
                            class="absolute inset-0"
                            :style="{ background: `linear-gradient(135deg, ${game.color}55, ${game.color}15)` }"
                        />

                        <!-- Fade to card body -->
                        <div
                            class="absolute inset-0"
                            :style="dark
                                ? 'background: linear-gradient(to bottom, transparent 20%, rgba(17,17,19,0.97) 100%)'
                                : 'background: linear-gradient(to bottom, transparent 20%, rgba(250,250,250,0.97) 100%)'"
                        />

                        <!-- Online badge -->
                        <div
                            v-if="game.online_servers > 0"
                            class="absolute top-2.5 right-2.5 flex items-center gap-1 px-2 py-1 rounded-full bg-black/50 backdrop-blur-sm border border-white/10"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                            <span class="text-[10px] font-semibold text-white">{{ game.online_servers }} online</span>
                        </div>

                        <!-- Game icon — KEEP! -->
                        <div
                            class="absolute bottom-2.5 left-3.5 w-9 h-9 rounded-lg overflow-hidden border shadow-md"
                            :class="dark ? 'border-white/15' : 'border-white/50'"
                        >
                            <GameIcon :slug="game.slug" :alt="game.name" />
                        </div>
                    </div>

                    <!-- Card body -->
                    <div class="px-4 pb-4 pt-2">
                        <h3 class="text-[14px] font-bold mb-3 truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-700'">
                            {{ game.name }}
                        </h3>

                        <!-- Stats row -->
                        <div class="flex items-center gap-4 mb-3">
                            <div>
                                <p class="text-[16px] font-bold leading-none tabular-nums" :class="dark ? 'text-zinc-100' : 'text-zinc-700'">{{ game.servers_count }}</p>
                                <p class="text-[10px] uppercase tracking-wider mt-0.5" :class="dark ? 'text-zinc-600' : 'text-zinc-500'">{{ t('servers.servers_label') }}</p>
                            </div>
                            <div class="w-px h-7" :class="dark ? 'bg-zinc-800' : 'bg-zinc-100'" />
                            <div>
                                <p
                                    class="text-[16px] font-bold leading-none tabular-nums"
                                    :style="{ color: game.players_online > 0 ? '#22c55e' : dark ? '#3f3f46' : '#a1a1aa' }"
                                >{{ game.players_online }}</p>
                                <p class="text-[10px] uppercase tracking-wider mt-0.5" :class="dark ? 'text-zinc-600' : 'text-zinc-500'">{{ t('servers.players_label') }}</p>
                            </div>
                            <div class="w-px h-7" :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'" />
                            <div class="flex-1">
                                <p class="text-[16px] font-bold leading-none tabular-nums" :class="dark ? 'text-zinc-100' : 'text-zinc-700'">
                                    {{ onlinePercent(game) }}<span class="text-[11px] font-normal" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">%</span>
                                </p>
                                <p class="text-[10px] uppercase tracking-wider mt-0.5" :class="dark ? 'text-zinc-600' : 'text-zinc-500'">{{ t('servers.online_pct') }}</p>
                            </div>
                        </div>

                        <!-- Online bar -->
                        <div class="w-full h-1 rounded-full overflow-hidden" :class="dark ? 'bg-zinc-800' : 'bg-zinc-100'">
                            <div class="h-full rounded-full transition-all duration-500" :style="{ width: onlinePercent(game) + '%', backgroundColor: game.color }" />
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="!filtered.length" class="text-center py-20">
                <Gamepad2 :size="36" :stroke-width="1.25" class="mx-auto mb-3" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
                <p class="text-[14px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('servers.no_games') }}</p>
            </div>
        </div>

    </PublicLayout>
</template>
