<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import ActivityPanel from '@/components/Servers/ActivityPanel.vue';
import ServerHero from '@/components/Servers/ServerHero.vue';
import ServerPlayers from '@/components/Servers/ServerPlayers.vue';
import ServerReviews from '@/components/Servers/ServerReviews.vue';
import ServerSidebar from '@/components/Servers/ServerSidebar.vue';
import type { GameInfo, InsightRange, Review, ServerStatus } from '@/components/Servers/types';
import { useLocale } from '@/composables/useLocale';

defineProps<{
    game: GameInfo;
    server: {
        id: number; ip: string; port: number; address: string; name: string;
        country_code: string | null; tags: string[]; is_favourited: boolean;
        status: (ServerStatus & {
            game_version: string | null;
            players: { name: string; score: number; duration: string }[];
        }) | null;
    };
    map_image: string | null;
    history_ranges: Record<'24h' | '7d' | '30d', InsightRange>;
    stats: {
        total_clicks: number; clicks_today: number; peak_players: number;
        uptime_24h: number; uptime_7d: number | null; uptime_30d: number | null;
    };
    uptime_daily: { date: string; pct: number | null }[];
    reviews: { data: Review[]; total: number };
    user_review: { rating: number; body: string | null } | null;
    average_rating: number | null;
}>();

const { t } = useLocale();
</script>

<template>
    <Head>
        <title>{{ server.name }}</title>
        <meta name="description" :content="t('servers.meta_show', { name: server.name, game: game.name })" />
    </Head>

    <PublicLayout>
        <ServerHero :game="game" :server="server" :uptime24h="stats.uptime_24h"
            :average-rating="average_rating" :review-count="reviews.total" />

        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-5">
                <div class="flex flex-col gap-5 min-w-0">
                    <ActivityPanel :ranges="history_ranges" :title="t('servers.player_history')"
                        :subtitle="t('servers.player_history_hint')" :chart-height="208" />
                    <ServerPlayers :online="!!server.status?.is_online" :players-max="server.status?.players_max ?? 0"
                        :players="server.status?.players ?? []" />
                    <ServerReviews :server-id="server.id" :reviews="reviews" :user-review="user_review" :average-rating="average_rating" />
                </div>

                <ServerSidebar :game="game" :server="server" :map-image="map_image" :stats="stats" :uptime-daily="uptime_daily">
                    <ExtensionSlot name="server.show.sidebar" :context="{ serverId: server.id }" />
                </ServerSidebar>
            </div>

            <ExtensionSlot name="server.show.tabs" :context="{ serverId: server.id }" />
        </div>
    </PublicLayout>
</template>
