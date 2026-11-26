<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import SocialLinks from '@/components/UI/SocialLinks.vue';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { safeUrl } from '@/lib/safeUrl';
import type { SharedProps } from './usePublicNav';

interface SiteFooter {
    games: { slug: string; name: string; servers: number; players: number }[];
    servers_online: number;
    servers_total: number;
    players_online: number;
}

const { t } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const page = usePage<SharedProps>();

const footerData = computed(() => (page.props.siteFooter as SiteFooter | null | undefined) ?? null);
const footerMenu = computed(() => page.props.menus?.footer_links ?? []);
const legalMenu = computed(() => page.props.menus?.footer_legal ?? []);
const legalPages = computed(() => (page.props.legalPages as { slug: string; title: string }[] | undefined) ?? []);
const extensionLinks = computed(() => (page.props.footerNav as { label: string; url: string }[] | undefined) ?? []);

/** Fallback when no footer menu is configured in admin. */
const communityLinks = computed(() => [
    { label: t('home.link_news'), href: route('news.index') },
    { label: t('members.title'), href: route('members.index') },
    { label: t('home.link_rules'), href: route('rules.index') },
    { label: t('home.footer_contact'), href: route('contact.show') },
]);

const accountLinks = computed(() => {
    const user = page.props.auth?.user;
    if (user) {
        return [
            ...(user.username ? [{ label: t('home.my_profile'), href: route('profile.show', { username: user.username }) }] : []),
            { label: t('home.my_servers'), href: route('account.favorites') },
            { label: t('navigation.settings'), href: route('account.index') },
        ];
    }

    return [
        { label: t('home.login'), href: route('login') },
        ...(page.props.registrationEnabled ? [{ label: t('home.create_account'), href: route('register') }] : []),
        { label: t('home.link_support'), href: route('contact.show') },
    ];
});

const year = new Date().getFullYear().toString();

const heading = computed(() => dark.value ? 'text-zinc-400' : 'text-zinc-500');
const link = computed(() => dark.value ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900');
const legal = computed(() => dark.value ? 'text-zinc-500 hover:text-zinc-400' : 'text-zinc-400 hover:text-zinc-500');
</script>

<template>
    <footer class="border-t transition-colors" :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200/70 bg-zinc-100'">
        <!-- Live strip. The footer is on every public page; most of them have no
             other indication that anything is running. -->
        <div v-if="footerData" class="border-b" :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-3 flex items-center gap-x-5 gap-y-1.5 flex-wrap text-[12px]">
                <span class="inline-flex items-center gap-2 font-bold" :class="dark ? 'text-emerald-400' : 'text-emerald-800'">
                    <span class="hc-live-dot" aria-hidden="true" />
                    {{ t('home.servers_live', { online: footerData.servers_online, total: footerData.servers_total }) }}
                </span>
                <span :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                    <strong class="font-bold tabular-nums" :class="dark ? 'text-zinc-200' : 'text-zinc-900'">{{ footerData.players_online.toLocaleString() }}</strong>
                    {{ t('home.stat_players').toLowerCase() }}
                </span>
                <Link :href="route('servers.index')"
                    class="group ml-auto inline-flex items-center gap-1.5 font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hc-accent/50 rounded px-1"
                    :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">
                    {{ t('home.browse_all') }}
                    <ArrowRight :size="12" :stroke-width="2" class="transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                </Link>
            </div>
        </div>

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))]">
                <div>
                    <Link :href="route('home')" class="inline-block mb-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hc-accent/50 rounded">
                        <span class="text-[15px] font-bold tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ page.props.app.name }}</span>
                    </Link>
                    <p class="text-[12.5px] leading-relaxed mb-4 max-w-[280px] text-zinc-500">{{ t('home.footer_tagline') }}</p>
                    <SocialLinks variant="boxed" />
                </div>

                <!-- Games actually hosted here — the one thing a player wants from a footer. -->
                <nav v-if="footerData?.games.length" :aria-label="t('home.footer_games')">
                    <h2 class="text-[11px] uppercase tracking-widest font-bold mb-3.5" :class="heading">{{ t('home.footer_games') }}</h2>
                    <ul class="flex flex-col gap-2">
                        <li v-for="g in footerData.games" :key="g.slug">
                            <Link :href="route('servers.game', g.slug)"
                                class="group flex items-center gap-2 text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hc-accent/50 rounded"
                                :class="link"
                                :title="t('home.footer_game_hint', { servers: g.servers, players: g.players })">
                                <span class="truncate">{{ g.name }}</span>
                                <span class="text-[11px] font-semibold tabular-nums shrink-0"
                                    :class="g.players > 0 ? (dark ? 'text-emerald-400' : 'text-emerald-800') : 'text-zinc-500'">{{ g.players }}</span>
                            </Link>
                        </li>
                    </ul>
                </nav>

                <nav :aria-label="t('home.footer_community')">
                    <h2 class="text-[11px] uppercase tracking-widest font-bold mb-3.5" :class="heading">{{ t('home.footer_community') }}</h2>
                    <ul class="flex flex-col gap-2">
                        <template v-if="footerMenu.length">
                            <li v-for="item in footerMenu" :key="item.label">
                                <a :href="safeUrl(item.url)" :target="item.target" :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
                                    class="text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hc-accent/50 rounded"
                                    :class="link">{{ item.label }}</a>
                            </li>
                        </template>
                        <template v-else>
                            <li v-for="item in communityLinks" :key="item.href">
                                <Link :href="item.href"
                                    class="text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hc-accent/50 rounded"
                                    :class="link">{{ item.label }}</Link>
                            </li>
                        </template>
                        <!-- Extension-registered footer links -->
                        <li v-for="item in extensionLinks" :key="item.url">
                            <a :href="safeUrl(item.url)"
                                class="text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hc-accent/50 rounded"
                                :class="link">{{ item.label }}</a>
                        </li>
                    </ul>
                </nav>

                <nav :aria-label="t('home.footer_account')">
                    <h2 class="text-[11px] uppercase tracking-widest font-bold mb-3.5" :class="heading">{{ t('home.footer_account') }}</h2>
                    <ul class="flex flex-col gap-2">
                        <li v-for="item in accountLinks" :key="item.href">
                            <Link :href="item.href"
                                class="text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hc-accent/50 rounded"
                                :class="link">{{ item.label }}</Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="border-t px-4 sm:px-6 py-4" :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
            <div class="max-w-[1600px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                    {{ t('home.footer_copyright').replace(':year', year) }}
                </p>
                <nav class="flex items-center flex-wrap gap-4" :aria-label="t('legal.breadcrumb')">
                    <!-- Menu-managed legal links take priority, then the legal pages themselves. -->
                    <template v-if="legalMenu.length">
                        <a v-for="item in legalMenu" :key="item.label" :href="safeUrl(item.url)" :target="item.target"
                            :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
                            class="text-[12px] transition-colors" :class="legal">{{ item.label }}</a>
                    </template>
                    <template v-else>
                        <Link v-for="p in legalPages" :key="p.slug" :href="`/legal/${p.slug}`"
                            class="text-[12px] transition-colors" :class="legal">{{ p.title }}</Link>
                    </template>
                </nav>
            </div>
        </div>
    </footer>
</template>
