<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Users, Search, X, MapPin, BadgeCheck, Wifi, Sprout, Medal, CircleCheck,
    Gamepad2, MessageSquare, Lock, Star, FileText, Mail, Puzzle,
    PenLine, Flame, Compass, MessagesSquare, Heart,
} from '@lucide/vue';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

interface Role { name: string; color: string; icon: string }
interface Member {
    id: number; name: string; username: string | null; avatar: string | null; banner: string | null;
    bio: string | null; location: string | null; role: Role | null;
    joined_at: string; verified: boolean; is_online?: boolean;
    achievements: string[];
}

const achievementIcons: Record<string, unknown> = {
    early_adopter: Sprout, veteran: Medal, verified: CircleCheck, steam_linked: Gamepad2,
    discord_linked: MessageSquare, secure: Lock, collector: Star, critic: FileText,
    socialite: Mail, complete_profile: Puzzle,
    reviewer_pro: PenLine, regular: Flame, explorer: Compass, commentator: MessagesSquare, popular: Heart,
};
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator {
    data: Member[]; links: PageLink[]; current_page: number; last_page: number;
    total: number; from: number | null; to: number | null; per_page: number;
}

const props = defineProps<{ members: Paginator; filters: { search: string }; total: number; online_count: number }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

// ── Live online count via Reverb presence channel ─────────────────────────
const liveOnlineCount = ref(props.online_count ?? 0);

/** Translated name for an achievement, falling back to the slug if a key is missing. */
function badgeLabel(slug: string): string {
    const key = `achievements.${slug}.label`;
    const label = t(key);
    return label === key ? slug : label;
}

function badgeDescription(slug: string): string {
    const key = `achievements.${slug}.description`;
    const description = t(key);
    return description === key ? badgeLabel(slug) : description;
}

/** Two named badges fit a card; the rest collapse into a "+N" with a tooltip. */
function visibleBadges(member: Member) {
    return member.achievements.slice(0, 2).map(slug => ({
        slug,
        icon: achievementIcons[slug] ?? BadgeCheck,
        label: badgeLabel(slug),
        description: badgeDescription(slug),
    }));
}

function remainingBadgeNames(member: Member): string {
    return member.achievements.slice(2).map(badgeLabel).join(', ');
}

const heroStats = computed(() => [
    {
        icon: Users,
        value: props.total.toLocaleString(),
        label: t('members.stat_total'),
        hint: t('members.stat_total_hint'),
        accent: false,
    },
    {
        icon: Wifi,
        value: liveOnlineCount.value.toLocaleString(),
        label: t('members.stat_online'),
        hint: t('members.stat_online_hint'),
        accent: liveOnlineCount.value > 0,
    },
]);

// ── Search ─────────────────────────────────────────────────────────────────
const search = ref(props.filters.search);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    // reset infinite scroll on new search
    allMembers.value = [];
    currentPage.value = 0;
    searchTimeout = setTimeout(() => {
        router.get(route('members.index'), value ? { search: value } : {}, {
            preserveState: true, preserveScroll: false, replace: true,
            onSuccess: () => {
                allMembers.value = props.members.data;
                currentPage.value = props.members.current_page;
            },
        });
    }, 350);
});

// ── Infinite scroll ────────────────────────────────────────────────────────
const allMembers = ref<Member[]>([...props.members.data]);
const currentPage = ref(props.members.current_page);
const loadingMore = ref(false);
const sentinelRef = ref<HTMLDivElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasMore = computed(() => currentPage.value < props.members.last_page);

async function loadMore() {
    if (loadingMore.value || !hasMore.value) return;
    loadingMore.value = true;
    const nextPage = currentPage.value + 1;
    await new Promise<void>((resolve) => {
        router.get(
            route('members.index'),
            { page: nextPage, ...(search.value ? { search: search.value } : {}) },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    allMembers.value = [...allMembers.value, ...props.members.data];
                    currentPage.value = props.members.current_page;
                    resolve();
                },
                onError: () => resolve(),
            },
        );
    });
    loadingMore.value = false;
}

let presenceChannel: ReturnType<typeof window.Echo.join> | null = null;

onMounted(() => {
    if (!('IntersectionObserver' in window)) return;
    observer = new IntersectionObserver(
        (entries) => { if (entries[0].isIntersecting) loadMore(); },
        { rootMargin: '200px' },
    );
    if (sentinelRef.value) observer.observe(sentinelRef.value);

    // Join presence channel to track live online count
    const Echo = (window as Record<string, unknown>).Echo as typeof window.Echo | undefined;
    if (Echo) {
        presenceChannel = Echo.join('online-users')
            .here((users: unknown[]) => { liveOnlineCount.value = users.length; })
            .joining(() => { liveOnlineCount.value++; })
            .leaving(() => { liveOnlineCount.value = Math.max(0, liveOnlineCount.value - 1); });
    }
});
onUnmounted(() => {
    observer?.disconnect();
    const Echo = (window as Record<string, unknown>).Echo as typeof window.Echo | undefined;
    Echo?.leave('online-users');
});

// ── Helpers ────────────────────────────────────────────────────────────────
function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[name.charCodeAt(0) % colors.length];
}

/** Falls back to a colour derived from the name when the member has no role. */
function accentColor(member: Member): string {
    return member.role?.color ?? avatarBg(member.name);
}
</script>

<template>
    <Head>
        <title>{{ t('members.title') }}</title>
        <meta name="description" :content="t('members.meta_description', {
            count: String(total),
            online: String(liveOnlineCount),
        })" />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <section
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
            :aria-label="t('members.title')"
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
                    { label: t('members.title') },
                ]" />

                <div class="grid gap-8 lg:gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,300px)] lg:items-end mt-4">

                    <div class="max-w-xl">
                        <!-- aria-live: the count updates over the presence channel -->
                        <div
                            class="hc-hero-in inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                            :class="liveOnlineCount > 0
                                ? (dark ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700')
                                : (dark ? 'border-zinc-700 bg-zinc-800/60 text-zinc-400' : 'border-zinc-300 bg-zinc-200/70 text-zinc-600')"
                            aria-live="polite"
                        >
                            <span v-if="liveOnlineCount > 0" class="hc-live-dot" aria-hidden="true" />
                            {{ t('members.online_now', { count: liveOnlineCount }) }}
                        </div>

                        <h1 class="hc-hero-in hc-hero-in--1 mt-4 text-[30px] sm:text-[40px] font-black tracking-tight leading-[1.07]"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ t('members.heading_1') }}
                            <span class="hc-hero-gradient bg-clip-text text-transparent">{{ t('members.heading_2') }}</span>
                        </h1>

                        <p class="hc-hero-in hc-hero-in--2 mt-3 text-[15px] leading-relaxed max-w-lg"
                           :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                            {{ t('members.hero_description') }}
                        </p>

                        <!-- Search -->
                        <div class="hc-hero-in hc-hero-in--3 mt-6 w-full max-w-md">
                            <label for="member-search" class="sr-only">{{ t('members.search_placeholder') }}</label>
                            <div class="relative">
                                <Search :size="16" :stroke-width="1.9" aria-hidden="true"
                                    class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'" />
                                <input
                                    id="member-search"
                                    v-model="search"
                                    type="search"
                                    autocomplete="off"
                                    :placeholder="t('members.search_placeholder')"
                                    class="w-full rounded-xl border pl-10 pr-10 py-3 text-[14px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                                    :class="dark
                                        ? 'border-zinc-800 bg-zinc-900/70 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50'
                                        : 'border-zinc-300 bg-white text-zinc-900 placeholder:text-zinc-500 focus:border-blue-500/60'"
                                />
                                <button v-if="search" type="button" @click="search = ''"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'text-zinc-500 hover:text-zinc-200 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200/70'"
                                    :aria-label="t('members.clear_search')" :title="t('members.clear_search')">
                                    <X :size="15" :stroke-width="2" aria-hidden="true" />
                                </button>
                            </div>
                            <p class="sr-only" role="status" aria-live="polite">
                                {{ t('members.results_count', { count: members.total }) }}
                            </p>
                        </div>
                    </div>

                    <dl class="hc-hero-in hc-hero-in--2 grid grid-cols-2 gap-2.5">
                        <div v-for="(item, i) in heroStats" :key="item.label"
                            class="hc-reveal rounded-xl border px-3 py-2.5 backdrop-blur-md"
                            :style="{ animationDelay: 0.18 + i * 0.06 + 's' }"
                            :class="dark
                                ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                                : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'"
                            :title="item.hint">
                            <component :is="item.icon" :size="13" :stroke-width="1.9"
                                class="mb-1.5" :class="item.accent ? 'text-emerald-500' : 'text-blue-500'"
                                aria-hidden="true" />
                            <dd class="text-[19px] font-black leading-none tabular-nums"
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
            <div v-if="allMembers.length === 0 && !loadingMore"
                class="flex flex-col items-center justify-center rounded-xl border py-20"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                <Users :size="32" :stroke-width="1.4" class="mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                <p class="text-[14px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                    {{ filters.search ? t('members.no_results').replace(':query', filters.search) : t('members.empty') }}
                </p>
            </div>

            <!-- Grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link
                    v-for="member in allMembers" :key="member.id"
                    :href="member.username ? route('profile.show', member.username) : '#'"
                    class="group flex flex-col rounded-2xl border overflow-hidden transition-all duration-200 hover:-translate-y-0.5"
                    :class="dark
                        ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/70 hover:shadow-lg hover:shadow-black/30'
                        : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-md hover:shadow-zinc-200/60 hover:border-zinc-300'"
                >
                    <!-- Banner (member's own banner, or a gradient in their role colour) -->
                    <div class="relative h-16 shrink-0 overflow-hidden">
                        <img v-if="member.banner" :src="member.banner" :alt="''" loading="lazy" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full"
                            :style="{ background: `linear-gradient(135deg, ${accentColor(member)}55 0%, ${accentColor(member)}18 60%, transparent 100%)` }" />
                        <div v-if="dark" class="absolute inset-0 opacity-30"
                            style="background-image:radial-gradient(circle,rgba(255,255,255,0.05) 1px,transparent 1px);background-size:16px 16px" />
                    </div>

                    <div class="flex flex-col gap-3 p-4 -mt-7">
                        <!-- Avatar + name -->
                        <div class="flex items-end gap-3">
                            <div class="relative w-14 h-14 rounded-xl shrink-0 overflow-hidden ring-4" :class="dark ? 'ring-[#111113]' : 'ring-white'">
                                <img v-if="member.avatar" :src="member.avatar" :alt="member.name" loading="lazy" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-[18px] font-black uppercase text-white"
                                    :style="{ backgroundColor: accentColor(member) }">
                                    {{ member.name.charAt(0) }}
                                </div>
                                <!-- Online dot -->
                                <span v-if="member.is_online"
                                    class="absolute bottom-0.5 right-0.5 w-3 h-3 rounded-full bg-emerald-400 border-2"
                                    :class="dark ? 'border-[#111113]' : 'border-white'" />
                            </div>
                            <div class="min-w-0 flex-1 pb-0.5">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-[14px] font-bold truncate transition-colors"
                                        :class="dark ? 'text-zinc-100 group-hover:text-blue-400' : 'text-zinc-800 group-hover:text-blue-600'">
                                        {{ member.name }}
                                    </p>
                                    <BadgeCheck v-if="member.verified" :size="13" :stroke-width="2.2" class="text-blue-400 shrink-0" />
                                </div>
                                <p v-if="member.username" class="text-[12px] font-mono truncate" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">@{{ member.username }}</p>
                            </div>
                        </div>

                        <!-- Role badge -->
                        <div v-if="member.role" class="flex">
                            <span class="inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-full border"
                                :style="{ backgroundColor: member.role.color + '1a', color: member.role.color, borderColor: member.role.color + '40' }">
                                {{ member.role.name }}
                            </span>
                        </div>

                        <!-- Bio -->
                        <p v-if="member.bio" class="text-[13px] leading-relaxed line-clamp-2" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ member.bio }}</p>

                        <!-- Badges. These used to be a row of 13px grey icons with
                             the raw slug as the tooltip, so "early_adopter" was the
                             best a reader could get. Each one now carries its
                             translated name, and the tooltip explains how it is
                             earned. -->
                        <ul v-if="member.achievements.length" class="flex flex-wrap items-center gap-1.5">
                            <li v-for="badge in visibleBadges(member)" :key="badge.slug">
                                <span class="inline-flex items-center gap-1.5 pl-1.5 pr-2 py-1 rounded-lg text-[11px] font-semibold"
                                    :class="dark ? 'bg-zinc-800/80 text-zinc-300' : 'bg-zinc-100 text-zinc-700'"
                                    :title="badge.description">
                                    <component :is="badge.icon" :size="12" :stroke-width="2"
                                        class="shrink-0 text-amber-500" aria-hidden="true" />
                                    {{ badge.label }}
                                </span>
                            </li>
                            <li v-if="member.achievements.length > 2">
                                <span class="inline-flex items-center px-2 py-1 rounded-lg text-[11px] font-bold"
                                    :class="dark ? 'bg-zinc-800/60 text-zinc-400' : 'bg-zinc-100 text-zinc-600'"
                                    :title="remainingBadgeNames(member)">
                                    +{{ member.achievements.length - 2 }}
                                </span>
                            </li>
                        </ul>

                        <!-- Footer -->
                        <div class="flex items-center gap-3 text-[11px] mt-auto pt-2.5 border-t"
                            :class="dark ? 'border-zinc-800/60 text-zinc-500' : 'border-zinc-200 text-zinc-500'">
                            <span v-if="member.location" class="flex items-center gap-1 truncate"
                                  :title="t('members.location_hint')">
                                <MapPin :size="11" :stroke-width="1.9" aria-hidden="true" />{{ member.location }}
                            </span>
                            <span class="ml-auto shrink-0" :title="t('members.joined_hint')">
                                {{ t('members.joined') }} {{ member.joined_at }}
                            </span>
                        </div>
                    </div>
                </Link>

                <!-- Skeleton cards while loading -->
                <template v-if="loadingMore">
                    <div v-for="n in 5" :key="`sk-${n}`"
                        class="flex flex-col rounded-2xl border overflow-hidden animate-pulse"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="h-16" :class="dark ? 'bg-zinc-800/60' : 'bg-zinc-100'" />
                        <div class="flex flex-col gap-3 p-4 -mt-7">
                            <div class="flex items-end gap-3">
                                <div class="w-14 h-14 rounded-xl shrink-0 ring-4" :class="dark ? 'bg-zinc-800 ring-[#111113]' : 'bg-zinc-200 ring-white'" />
                                <div class="flex-1 space-y-1.5 pb-1">
                                    <div class="h-3 rounded-full w-24" :class="dark ? 'bg-zinc-800' : 'bg-zinc-100'" />
                                    <div class="h-2.5 rounded-full w-16" :class="dark ? 'bg-zinc-800/60' : 'bg-zinc-100'" />
                                </div>
                            </div>
                            <div class="h-2.5 rounded-full w-20" :class="dark ? 'bg-zinc-800/60' : 'bg-zinc-100'" />
                        </div>
                    </div>
                </template>
            </div>

            <!-- Infinite scroll sentinel -->
            <div ref="sentinelRef" class="h-8 mt-4" />

            <!-- End of list -->
            <p v-if="!hasMore && allMembers.length > 0" class="text-center text-[12px] mt-2"
                :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                All {{ total.toLocaleString() }} members loaded
            </p>

        </div>
    </PublicLayout>
</template>
