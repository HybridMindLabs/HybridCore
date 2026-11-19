<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import PublicSidebar from '@/components/UI/PublicSidebar.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import HomeHero from '@/components/Home/HomeHero.vue';
import HomeLatestNews from '@/components/Home/HomeLatestNews.vue';
import HomeServerList from '@/components/Home/HomeServerList.vue';
import HomeFeaturedSlider from '@/components/Home/HomeFeaturedSlider.vue';
import HomeRecentMembers from '@/components/Home/HomeRecentMembers.vue';
import HomePresence from '@/components/Home/HomePresence.vue';
import { useLocale } from '@/composables/useLocale';
import type { GameFilter, GameStat, HomeServer, NetworkStats, NewsCard, Presence, RecentMember } from '@/components/Home/types';

const props = defineProps<{
    servers: HomeServer[];
    games: GameFilter[];
    gameStats: GameStat[];
    totalPlayers: number;
    maxPlayers: number;
    viewer: { banner: string | null; role: { name: string; color: string } | null; achievements: string[] } | null;
    stats: NetworkStats;
    latestNews: NewsCard[];
    whoIsOnline: Presence | null;
    activeToday: Presence | null;
    communityActivity: { type: string; username: string | null; avatar: string | null; params: Record<string, string | number>; text?: string; at: string; url: string | null }[];
    preferredGameSlugs: string[];
    recentMembers: RecentMember[];
}>();

const { t } = useLocale();
const page = usePage<{ app: { name: string } }>();
const pageTitle = computed(() => `${page.props.app.name} - Gaming Network`);
const metaDescription = computed(() => t('home.meta_description', {
    servers: String(props.stats.servers),
    games: String(props.stats.games),
}));
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta name="description" :content="metaDescription" />
    </Head>

    <PublicLayout>
        <HomeHero :stats="stats" :game-stats="gameStats" :total-players="totalPlayers" :max-players="maxPlayers" />

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-6">
            <div class="grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)_300px] gap-6">

                <!-- Sidebar -->
                <div class="xl:order-first order-last flex flex-col gap-4">
                    <PublicSidebar :viewer="viewer" :stats="stats" :community-activity="communityActivity" />
                    <ExtensionSlot name="home.left.bottom" />
                </div>

                <!-- Middle column: News + Servers -->
                <div class="min-w-0 flex flex-col gap-6">
                    <ExtensionSlot name="home.middle.top" />
                    <HomeLatestNews v-if="latestNews.length" :news="latestNews" />
                    <HomeServerList
                        :servers="servers"
                        :games="games"
                        :game-stats="gameStats"
                        :total-players="totalPlayers"
                        :max-players="maxPlayers"
                        :preferred-game-slugs="preferredGameSlugs" />
                    <ExtensionSlot name="home.middle.bottom" />
                </div>

                <!-- ── Right column ──
                     Visible from lg and stacked below the content on smaller
                     screens. It used to be `hidden xl:flex`, which silently
                     dropped the featured servers and the presence lists for
                     everyone under 1280px. -->
                <div class="flex flex-col gap-3">
                    <ExtensionSlot name="home.right.top" />
                    <HomeFeaturedSlider :servers="servers" />
                    <HomeRecentMembers v-if="recentMembers.length" :members="recentMembers" />
                    <HomePresence :online="whoIsOnline" :today="activeToday" />
                    <ExtensionSlot name="home.right.bottom" />
                </div>

            </div>
        </div>
    </PublicLayout>
</template>
