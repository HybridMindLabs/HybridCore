<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    MapPin, Globe, Calendar, BadgeCheck, Pencil,
    MessageSquare, Ban, UserCheck, UserPlus, Wifi, WifiOff, Users, Star,
    Activity as ActivityIcon,
    Trophy, ShieldOff, Sprout, Medal, CircleCheck, Gamepad2,
    Lock, FileText, Mail, Puzzle,
    PenLine, Flame, Compass, MessagesSquare, Heart,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, reactive, ref } from 'vue';

interface Role { name: string; color: string; icon: string }
interface Achievement { slug: string; awarded_at: string }
interface FavServer {
    id: number; name: string; game: string | null; game_slug: string | null; game_icon: string | null;
    map: string | null; map_image: string | null;
    players: number | null; max_players: number | null; online: boolean;
    connect_url: string | null; show_route: string | null;
}
interface Profile {
    id: number; username: string; display_name: string; avatar: string | null; banner: string | null;
    bio: string | null; location: string | null; website: string | null;
    role: Role | null; joined_at: string; verified: boolean; is_self: boolean;
    can_message: boolean; is_blocked: boolean; is_viewer_member: boolean;
    achievements: Achievement[]; favourite_servers: FavServer[];
    stats: { joined_days_ago: number; favourite_servers_count: number };
    is_online: boolean; last_seen_at: string | null;
    followers_count: number; following_count: number; is_following: boolean;
}
interface ActivityItem { type: string; params: Record<string, string | number>; at: string; url: string | null }
interface MiniUser { username: string | null; name: string; avatar: string | null }

interface ExtensionPanel { key: string; label: string; icon: string; component: string; props: Record<string, unknown> }

const props = defineProps<{
    profile: Profile;
    activity: ActivityItem[];
    followers: MiniUser[];
    following: MiniUser[];
    extensionPanels?: ExtensionPanel[];
}>();

const openFollowList = ref<'followers' | 'following' | null>(null);

function toggleFollowList(which: 'followers' | 'following') {
    openFollowList.value = openFollowList.value === which ? null : which;
}
const { theme } = useTheme();
const { t, currentLocale } = useLocale();
const dark = computed(() => theme.value === 'dark');

const failedMapImages = reactive(new Set<number>());
const blockPending = ref(false);
const followPending = ref(false);

function toggleFollow() {
    if (followPending.value) return;
    followPending.value = true;
    const opts = { preserveScroll: true, onFinish: () => { followPending.value = false; } };
    if (props.profile.is_following) {
        router.delete(route('profile.unfollow', props.profile.id), opts);
    } else {
        router.post(route('profile.follow', props.profile.id), {}, opts);
    }
}

const activityIcons: Record<string, unknown> = { badge: Trophy, review: Star, comment: MessageSquare };

function activityLabel(item: ActivityItem): string {
    if (item.type === 'badge') {
        return t('profile.activity_badge', { badge: badgeLabel(String(item.params.badge_slug)) });
    }
    if (item.type === 'review') {
        return t('profile.activity_review', { name: String(item.params.name), rating: String(item.params.rating) });
    }
    return t('profile.activity_comment', { title: String(item.params.title) });
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[name.charCodeAt(0) % colors.length];
}
const accentColor = computed(() => props.profile.role?.color ?? '#3b82f6');

const achievementMeta: Record<string, { icon: unknown; color: string }> = {
    early_adopter:    { icon: Sprout,         color: '#f59e0b' },
    veteran:          { icon: Medal,          color: '#8b5cf6' },
    verified:         { icon: CircleCheck,    color: '#3b82f6' },
    steam_linked:     { icon: Gamepad2,       color: '#1b2838' },
    discord_linked:   { icon: MessageSquare,  color: '#5865f2' },
    secure:           { icon: Lock,           color: '#10b981' },
    collector:        { icon: Star,           color: '#eab308' },
    critic:           { icon: FileText,       color: '#ec4899' },
    socialite:        { icon: Mail,           color: '#f472b6' },
    complete_profile: { icon: Puzzle,         color: '#22c55e' },
    reviewer_pro:     { icon: PenLine,        color: '#a855f7' },
    regular:          { icon: Flame,          color: '#f97316' },
    explorer:         { icon: Compass,        color: '#06b6d4' },
    commentator:      { icon: MessagesSquare, color: '#14b8a6' },
    popular:          { icon: Heart,          color: '#ef4444' },
};

function badgeLabel(slug: string): string {
    const key = 'achievements.' + slug + '.label';
    const translated = t(key);
    return translated === key ? slug : translated;
}

function badgeDescription(slug: string): string {
    const key = 'achievements.' + slug + '.description';
    const translated = t(key);
    return translated === key ? '' : translated;
}

function formatAwardedAt(date: string): string {
    return new Date(date).toLocaleDateString(currentLocale.value, { year: 'numeric', month: 'short', day: 'numeric' });
}

function badgeTooltip(ach: Achievement): string {
    const desc = badgeDescription(ach.slug);
    return `${desc ? desc + ' — ' : ''}${t('profile.earned', { date: formatAwardedAt(ach.awarded_at) })}`;
}

function toggleBlock() {
    if (blockPending.value) return;
    blockPending.value = true;
    if (props.profile.is_blocked) {
        router.delete(route('account.unblock', props.profile.id), { onFinish: () => { blockPending.value = false; } });
    } else {
        router.post(route('account.block', props.profile.id), {}, { onFinish: () => { blockPending.value = false; } });
    }
}
</script>

<template>
    <Head>
        <title>{{ profile.display_name || profile.username }}</title>
        <meta name="description" :content="t('profile.meta_description', {
            name: profile.display_name || profile.username,
        })" />
    </Head>

    <PublicLayout>
        <div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-8">

            <Breadcrumb :items="[
                { label: t('navigation.nav_home'), href: route('home') },
                { label: t('profile.back_members'), href: route('members.index') },
                { label: profile.display_name || profile.username },
            ]" />

            <!-- Profile card -->
            <div class="rounded-2xl border overflow-hidden mb-4"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_2px_12px_rgba(0,0,0,0.07)]'">

                <!-- Banner -->
                <div class="relative h-48 overflow-hidden" :class="dark ? 'bg-[#0d0d0f]' : 'bg-zinc-100'">
                    <!-- Decorative: the profile is already named by the heading below. -->
                    <img v-if="profile.banner" :src="profile.banner" class="w-full h-full object-cover" alt="" />
                    <template v-else>
                        <div class="absolute inset-0" :style="`background:linear-gradient(125deg,${accentColor}22 0%,${accentColor}08 45%,transparent 70%)`" />
                        <div class="absolute inset-0" :style="`background:radial-gradient(ellipse at 80% 50%,${accentColor}18 0%,transparent 60%)`" />
                        <div v-if="dark" class="absolute inset-0 opacity-40" style="background-image:radial-gradient(circle,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:22px 22px" />
                    </template>
                    <div class="absolute bottom-0 left-0 right-0 h-20" :class="dark ? 'bg-gradient-to-t from-[#111113] to-transparent' : 'bg-gradient-to-t from-white to-transparent'" />

                    <!-- Edit / action buttons -->
                    <div class="absolute top-4 right-4 left-4 flex items-center justify-end gap-2 flex-wrap">
                        <Link v-if="profile.is_self" :href="route('account.index')"
                            class="flex items-center gap-1.5 text-[12px] font-semibold rounded-xl border px-3 py-2 transition backdrop-blur-sm"
                            :class="dark ? 'border-zinc-700/60 bg-zinc-900/70 text-zinc-300 hover:text-white' : 'border-zinc-200/80 bg-white/80 text-zinc-600 hover:text-zinc-900'">
                            <Pencil :size="12" :stroke-width="1.8" aria-hidden="true" /> {{ t('profile.edit_profile') }}
                        </Link>
                        <template v-if="!profile.is_self && profile.is_viewer_member">
                            <button type="button"
                                class="flex items-center gap-1.5 text-[12px] font-bold rounded-xl border px-3 py-2 transition backdrop-blur-sm"
                                :class="profile.is_following
                                    ? (dark ? 'border-zinc-700/60 bg-zinc-900/70 text-zinc-300 hover:text-red-400 hover:border-red-500/40' : 'border-zinc-200/80 bg-white/80 text-zinc-600 hover:text-red-500')
                                    : (dark ? 'border-emerald-500/40 bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100')"
                                :disabled="followPending"
                                @click="toggleFollow">
                                <component :is="profile.is_following ? UserCheck : UserPlus" :size="12" :stroke-width="2" />
                                {{ profile.is_following ? t('profile.following_btn') : t('profile.follow') }}
                            </button>
                            <Link v-if="profile.can_message" :href="route('account.messages.start')" method="post" as="button"
                                :data="{ username: profile.username }"
                                class="flex items-center gap-1.5 text-[12px] font-bold rounded-xl border px-3 py-2 transition backdrop-blur-sm"
                                :class="dark ? 'border-blue-500/40 bg-blue-500/20 text-blue-300 hover:bg-blue-500/30' : 'border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100'">
                                <MessageSquare :size="12" :stroke-width="2" /> {{ t('profile.message') }}
                            </Link>
                            <button type="button"
                                class="flex items-center gap-1.5 text-[12px] font-bold rounded-xl border px-3 py-2 transition backdrop-blur-sm"
                                :class="profile.is_blocked
                                    ? (dark ? 'border-emerald-500/40 bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30' : 'border-emerald-200 bg-emerald-50 text-emerald-700')
                                    : (dark ? 'border-zinc-700/60 bg-zinc-900/70 text-zinc-400 hover:text-red-400 hover:border-red-500/40' : 'border-zinc-200/80 bg-white/80 text-zinc-500 hover:text-red-500')"
                                :disabled="blockPending"
                                @click="toggleBlock">
                                <component :is="profile.is_blocked ? UserCheck : Ban" :size="12" :stroke-width="2" />
                                {{ profile.is_blocked ? t('profile.unblock') : t('profile.block') }}
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Avatar + Name row -->
                <div class="px-6 sm:px-10 -mt-14 pb-6 relative z-10">
                    <div class="flex items-end gap-5 flex-wrap">
                        <div class="relative shrink-0">
                            <div class="w-[88px] h-[88px] rounded-2xl overflow-hidden ring-4" :class="dark ? 'ring-[#111113]' : 'ring-white'">
                                <img v-if="profile.avatar" :src="profile.avatar" :alt="profile.username" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-[34px] font-black uppercase text-white" :style="{ backgroundColor: avatarBg(profile.username) }">{{ profile.username.charAt(0) }}</div>
                            </div>
                            <span v-if="profile.is_online" class="absolute bottom-1 right-1 w-4 h-4 rounded-full bg-emerald-400 ring-4"
                                :class="dark ? 'ring-[#111113]' : 'ring-white'"
                                role="img" :aria-label="t('profile.active_now')" :title="t('profile.active_now')" />
                        </div>
                        <div class="flex-1 min-w-0 pb-1.5">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <h1 class="text-[26px] font-black tracking-tight leading-none" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                    {{ profile.display_name || profile.username }}
                                </h1>
                                <BadgeCheck v-if="profile.verified" :size="18" :stroke-width="2.2" class="text-blue-400 shrink-0" />
                            </div>
                            <p class="text-[13px] font-mono mt-1" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">@{{ profile.username }}</p>
                            <div class="flex items-center gap-2 mt-2.5 flex-wrap">
                                <span v-if="profile.role" class="inline-flex items-center text-[12px] font-bold px-3 py-1 rounded-full border"
                                    :style="{ backgroundColor: profile.role.color + '18', color: profile.role.color, borderColor: profile.role.color + '38' }">
                                    {{ profile.role.name }}
                                </span>
                                <span v-if="profile.is_self" class="text-[11px] font-bold px-2.5 py-1 rounded-full border"
                                    :class="dark ? 'border-zinc-700/60 bg-zinc-800/60 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                                    {{ t('profile.you') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold"
                                    :class="profile.is_online ? 'text-emerald-400' : (dark ? 'text-zinc-600' : 'text-zinc-400')">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="profile.is_online ? 'bg-emerald-400' : 'bg-zinc-500'" />
                                    {{ profile.is_online ? t('profile.active_now') : (profile.last_seen_at ? t('profile.active_ago', { time: profile.last_seen_at }) : t('profile.offline')) }}
                                </span>
                            </div>
                        </div>

                        <!-- Stats row -->
                        <div class="flex items-center gap-4 pb-1.5 flex-wrap">
                            <div v-for="(stat, i) in [
                                { value: profile.stats.joined_days_ago,    label: t('profile.stat_days'),      hint: t('profile.stat_days_hint'),      list: null },
                                { value: profile.favourite_servers.length, label: t('profile.stat_favorites'), hint: t('profile.stat_favorites_hint'), list: null },
                                { value: profile.achievements.length,      label: t('profile.stat_badges'),    hint: t('profile.stat_badges_hint'),    list: null },
                                { value: profile.followers_count,          label: t('profile.stat_followers'), hint: t('profile.stat_followers_hint'), list: 'followers' as const },
                                { value: profile.following_count,          label: t('profile.stat_following'), hint: t('profile.stat_following_hint'), list: 'following' as const },
                            ]" :key="stat.label" class="flex items-center gap-4">
                                <div v-if="i > 0" class="w-px h-8" :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'" aria-hidden="true" />
                                <component
                                    :is="stat.list ? 'button' : 'div'"
                                    :type="stat.list ? 'button' : undefined"
                                    class="text-center rounded-lg px-1"
                                    :class="stat.list ? 'transition hover:opacity-70 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50' : ''"
                                    :title="stat.hint"
                                    :aria-expanded="stat.list ? openFollowList === stat.list : undefined"
                                    @click="stat.list && toggleFollowList(stat.list)"
                                >
                                    <p class="text-[18px] font-black tabular-nums"
                                        :class="openFollowList === stat.list && stat.list ? 'text-blue-500' : (dark ? 'text-zinc-100' : 'text-zinc-900')">{{ stat.value }}</p>
                                    <p class="text-[10px] uppercase tracking-wider font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ stat.label }}</p>
                                </component>
                            </div>
                        </div>
                    </div>

                    <p v-if="profile.bio" class="text-[14px] leading-relaxed mt-5 max-w-2xl" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ profile.bio }}</p>

                    <div v-if="profile.location || profile.website || profile.joined_at"
                        class="flex flex-wrap items-center gap-5 mt-5 pt-5 border-t"
                        :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                        <span v-if="profile.location" class="flex items-center gap-2 text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            <MapPin :size="14" :stroke-width="1.8" :class="dark ? 'text-zinc-600' : 'text-zinc-400'" /> {{ profile.location }}
                        </span>
                        <a v-if="profile.website" :href="profile.website" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-[13px] text-blue-400 hover:text-blue-300 transition-colors">
                            <Globe :size="14" :stroke-width="1.8" /> {{ profile.website.replace(/^https?:\/\//, '') }}
                        </a>
                        <span class="flex items-center gap-2 text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            <Calendar :size="14" :stroke-width="1.8" :class="dark ? 'text-zinc-600' : 'text-zinc-400'" /> {{ t('profile.joined') }} {{ profile.joined_at }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Followers / Following list (expands from the stat counters) -->
            <div v-if="openFollowList" class="rounded-2xl border overflow-hidden mb-4"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                <div class="px-5 py-3.5 border-b flex items-center justify-between" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                    <div class="flex items-center gap-2">
                        <Users :size="14" :stroke-width="1.8" :class="dark ? 'text-blue-400' : 'text-blue-500'" />
                        <p class="text-[13px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ openFollowList === 'followers' ? t('profile.followers_title') : t('profile.following_title') }}
                        </p>
                        <span class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                            ({{ openFollowList === 'followers' ? profile.followers_count : profile.following_count }})
                        </span>
                    </div>
                    <button type="button" class="text-[12px] transition" :class="dark ? 'text-zinc-600 hover:text-zinc-300' : 'text-zinc-400 hover:text-zinc-600'"
                        @click="openFollowList = null">{{ t('profile.close') }}</button>
                </div>
                <div v-if="(openFollowList === 'followers' ? followers : following).length"
                    class="p-4 flex flex-wrap gap-2">
                    <component
                        :is="mini.username ? Link : 'div'"
                        v-for="mini in (openFollowList === 'followers' ? followers : following)"
                        :key="mini.username ?? mini.name"
                        :href="mini.username ? route('profile.show', mini.username) : undefined"
                        class="flex items-center gap-2 pl-1.5 pr-3 py-1.5 rounded-full border text-[12px] font-semibold transition"
                        :class="dark ? 'border-zinc-800 bg-zinc-900/50 text-zinc-300 hover:border-zinc-600' : 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:border-zinc-400'"
                    >
                        <span class="w-6 h-6 rounded-full overflow-hidden shrink-0">
                            <img v-if="mini.avatar" :src="mini.avatar" class="w-full h-full object-cover" :alt="mini.name" />
                            <span v-else class="w-full h-full flex items-center justify-center text-[10px] font-black text-white uppercase"
                                :style="{ backgroundColor: avatarBg(mini.name) }">{{ mini.name[0] }}</span>
                        </span>
                        {{ mini.username ?? mini.name }}
                    </component>
                </div>
                <div v-else class="px-5 py-8 text-center text-[13px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                    {{ openFollowList === 'followers' ? t('profile.no_followers') : t('profile.not_following') }}
                </div>
            </div>

            <!-- Achievements + Favorite servers row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Achievements -->
                <div v-if="profile.achievements.length > 0" class="rounded-2xl border overflow-hidden"
                    :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                    <div class="px-5 py-3.5 border-b flex items-center gap-2" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                        <Trophy :size="14" :stroke-width="1.8" :class="dark ? 'text-amber-400' : 'text-amber-500'" />
                        <p class="text-[13px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('profile.badges_title') }}</p>
                    </div>
                    <div class="p-4 flex flex-wrap gap-2">
                        <div v-for="ach in profile.achievements" :key="ach.slug"
                            :title="badgeTooltip(ach)"
                            class="flex items-center gap-2.5 pl-2.5 pr-3 py-2 rounded-xl border text-[12px] font-semibold transition cursor-help"
                            :class="dark ? 'border-zinc-800 bg-zinc-900/50 text-zinc-300 hover:border-zinc-700' : 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:border-zinc-300'">
                            <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                                :style="{ backgroundColor: (achievementMeta[ach.slug]?.color ?? '#71717a') + '1c' }">
                                <component :is="achievementMeta[ach.slug]?.icon ?? Trophy" :size="14" :stroke-width="2"
                                    :style="{ color: achievementMeta[ach.slug]?.color }" />
                            </span>
                            <span class="flex flex-col leading-tight">
                                <span>{{ badgeLabel(ach.slug) }}</span>
                                <span class="text-[10px] font-normal" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                    {{ formatAwardedAt(ach.awarded_at) }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Favourite Servers -->
                <div v-if="profile.favourite_servers.length > 0" class="rounded-2xl border overflow-hidden"
                    :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                    <div class="px-5 py-3.5 border-b flex items-center gap-2" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                        <Star :size="14" :stroke-width="1.8" :class="dark ? 'text-amber-400' : 'text-amber-500'" />
                        <p class="text-[13px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('profile.favourite_servers_title') }}</p>
                    </div>
                    <div class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-100'">
                        <div v-for="s in profile.favourite_servers" :key="s.id"
                            class="flex items-center gap-3 px-5 py-3 transition-colors"
                            :class="dark ? 'hover:bg-white/[0.02]' : 'hover:bg-zinc-50/80'">
                            <component :is="s.show_route ? Link : 'div'" :href="s.show_route ?? undefined" class="flex items-center gap-3 flex-1 min-w-0 group">
                                <div class="w-14 h-8 rounded-lg overflow-hidden shrink-0 border" :class="dark ? 'border-zinc-800 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'">
                                    <img v-if="s.map_image && !failedMapImages.has(s.id)" :src="s.map_image" :alt="s.map ?? ''" class="w-full h-full object-cover"
                                        @error="failedMapImages.add(s.id)" />
                                    <img v-else-if="s.game_icon" :src="s.game_icon" class="w-full h-full object-contain p-1.5" alt="" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-bold truncate transition-colors"
                                        :class="s.show_route ? (dark ? 'text-zinc-100 group-hover:text-blue-400' : 'text-zinc-900 group-hover:text-blue-600') : (dark ? 'text-zinc-100' : 'text-zinc-900')">
                                        {{ s.name }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="flex items-center gap-1 text-[11px]" :class="s.online ? 'text-emerald-400' : (dark ? 'text-zinc-600' : 'text-zinc-400')">
                                            <component :is="s.online ? Wifi : WifiOff" :size="9" :stroke-width="2" />
                                            {{ s.online ? t('servers.online') : t('servers.offline') }}
                                        </span>
                                        <span v-if="s.players !== null" class="flex items-center gap-1 text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                            <Users :size="9" :stroke-width="2" /> {{ s.players }}/{{ s.max_players }}
                                        </span>
                                        <span v-if="s.map" class="text-[11px] font-mono truncate" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ s.map }}</span>
                                    </div>
                                </div>
                            </component>
                            <a v-if="s.connect_url" :href="s.connect_url" class="shrink-0 text-[11px] font-bold px-2.5 py-1.5 rounded-lg border transition"
                                :class="dark ? 'border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/10' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'">
                                {{ t('profile.connect') }}
                            </a>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Extension-registered profile panels -->
            <component
                v-for="panel in (extensionPanels ?? [])"
                :key="panel.key"
                :is="panel.component"
                v-bind="panel.props"
                class="mt-4"
            />

            <!-- Recent activity -->
            <div v-if="activity.length" class="rounded-2xl border overflow-hidden mt-4"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                <div class="px-5 py-3.5 border-b flex items-center gap-2" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                    <ActivityIcon :size="14" :stroke-width="1.8" :class="dark ? 'text-blue-400' : 'text-blue-500'" />
                    <p class="text-[13px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('profile.recent_activity') }}</p>
                </div>
                <div class="divide-y" :class="dark ? 'divide-zinc-800/50' : 'divide-zinc-100'">
                    <component
                        :is="item.url ? Link : 'div'"
                        v-for="(item, i) in activity"
                        :key="i"
                        :href="item.url ?? undefined"
                        class="flex items-center gap-3 px-5 py-3 transition-colors"
                        :class="[item.url ? (dark ? 'hover:bg-white/[0.02] group' : 'hover:bg-zinc-50/80 group') : '']"
                    >
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                            :class="dark ? 'bg-zinc-800/80' : 'bg-zinc-100'">
                            <component :is="activityIcons[item.type] ?? ActivityIcon" :size="13" :stroke-width="1.8"
                                :class="item.type === 'badge' ? 'text-amber-400' : item.type === 'review' ? 'text-violet-400' : 'text-emerald-400'" />
                        </span>
                        <p class="flex-1 min-w-0 text-[13px] truncate transition-colors"
                            :class="[dark ? 'text-zinc-300' : 'text-zinc-700', item.url ? (dark ? 'group-hover:text-blue-400' : 'group-hover:text-blue-600') : '']">
                            {{ activityLabel(item) }}
                        </p>
                        <span class="text-[11px] shrink-0" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ item.at }}</span>
                    </component>
                </div>
            </div>

            <ExtensionSlot name="profile.tabs" :context="{ userId: profile.id }" />
        </div>
    </PublicLayout>
</template>
