<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LogIn, Users, Star, ShieldCheck, ArrowRight,
    Gamepad2, UsersRound,
    BookOpen, MessageSquare, HelpCircle, Newspaper, Trophy,
    Sprout, Medal, CircleCheck, Lock, FileText, Mail, Puzzle,
    PenLine, Flame, Compass, MessagesSquare, Heart, Activity,
} from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

interface Viewer { banner: string | null; role: { name: string; color: string } | null; achievements: string[] }
interface CommunityActivityItem { type: string; username: string | null; avatar: string | null; params: Record<string, string | number>; text?: string; at: string; url: string | null }

const props = defineProps<{
    viewer: Viewer | null;
    stats: { games: number; servers: number; members: number; online_servers: number };
    communityActivity: CommunityActivityItem[];
}>();

const achievementIcons: Record<string, unknown> = {
    early_adopter: Sprout, veteran: Medal, verified: CircleCheck, steam_linked: Gamepad2,
    discord_linked: MessageSquare, secure: Lock, collector: Star, critic: FileText,
    socialite: Mail, complete_profile: Puzzle,
    reviewer_pro: PenLine, regular: Flame, explorer: Compass, commentator: MessagesSquare, popular: Heart,
};

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ auth: { user: any } | null; [key: string]: unknown }>();

const viewerAccent = computed(() => {
    if (props.viewer?.role) return props.viewer.role.color;
    const name = page.props.auth?.user?.name ?? '?';
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[name.charCodeAt(0) % colors.length];
});

// ─── Color tokens ──────────────────────────────────────────────────────────
// Layer 0 → page bg  (dark: #09090b,  light: zinc-100)
// Layer 1 → card bg  (dark: #111113,  light: white)
// Layer 2 → section  (dark: #1a1a1e,  light: zinc-50)

const card      = computed(() => dark.value ? 'border-zinc-800/70 bg-[#111113]'  : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]');
const cardHead  = computed(() => dark.value ? 'border-zinc-800/60 bg-[#1a1a1e]'  : 'border-zinc-100   bg-zinc-50');
const divider   = computed(() => dark.value ? 'divide-zinc-800/60'               : 'divide-zinc-100');
const rowHover  = computed(() => dark.value ? 'hover:bg-white/[0.04]'            : 'hover:bg-zinc-50');
const textPri   = computed(() => dark.value ? 'text-zinc-100'                    : 'text-zinc-800');
const textSec   = computed(() => dark.value ? 'text-zinc-400'                    : 'text-zinc-500');
const textMute  = computed(() => dark.value ? 'text-zinc-500'                    : 'text-zinc-400');
const iconBg    = computed(() => dark.value ? 'bg-zinc-800/80'                   : 'bg-zinc-100');
const btnBorder = computed(() => dark.value
    ? 'border-zinc-700/70 text-zinc-400 hover:border-zinc-600 hover:text-zinc-100 hover:bg-white/[0.05]'
    : 'border-zinc-200   text-zinc-500 hover:border-zinc-300 hover:text-zinc-800 hover:bg-zinc-50');

function activityLabel(item: CommunityActivityItem): string {
    // Extension rows carry a pre-localized `text`.
    if (item.text) return item.text;
    if (item.type === 'badge') {
        const key = 'achievements.' + String(item.params.badge_slug) + '.label';
        const badge = t(key);
        return t('home.activity_badge', { badge: badge === key ? String(item.params.badge_slug) : badge });
    }
    if (item.type === 'review') {
        return t('home.activity_review', { name: String(item.params.name), rating: String(item.params.rating) });
    }
    return t('home.activity_comment', { title: String(item.params.title) });
}

const guestPerks = computed(() => [
    { icon: Star,          label: t('home.perk_favourites'), color: 'text-amber-400'   },
    { icon: Trophy,        label: t('home.perk_badges'),     color: 'text-violet-400'  },
    { icon: MessageSquare, label: t('home.perk_messages'),   color: 'text-emerald-400' },
]);

const quickLinks = computed(() => {
    const social = (page.props.socialLinks as Record<string, string> | undefined) ?? {};

    return [
        { icon: BookOpen,      label: t('home.link_rules'),   href: route('rules.index'),   external: false },
        { icon: Newspaper,     label: t('home.link_news'),    href: route('news.index'),    external: false },
        { icon: Users,         label: t('members.title'),     href: route('members.index'), external: false },
        { icon: HelpCircle,    label: t('home.link_support'), href: route('contact.show'),  external: false },
        // Only when a Discord URL is configured in admin settings
        ...(social.discord
            ? [{ icon: MessageSquare, label: t('home.link_discord'), href: social.discord, external: true }]
            : []),
    ];
});

</script>

<template>
    <aside class="flex flex-col gap-4">

        <!-- ── Auth card ── -->
        <div class="hc-reveal rounded-xl border overflow-hidden" :class="card">
            <!-- Logged in -->
            <template v-if="page.props.auth?.user">
                <!-- Banner: user's own, or a gradient in their role colour -->
                <div class="relative h-14 overflow-hidden">
                    <img v-if="viewer?.banner" :src="viewer.banner" alt="" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full"
                        :style="{ background: `linear-gradient(135deg, ${viewerAccent}55 0%, ${viewerAccent}18 60%, transparent 100%)` }" />
                    <div v-if="dark" class="absolute inset-0 opacity-30"
                        style="background-image:radial-gradient(circle,rgba(255,255,255,0.05) 1px,transparent 1px);background-size:16px 16px" />
                </div>

                <div class="px-4 -mt-6 pb-3">
                    <div class="flex items-end gap-3">
                        <div class="relative shrink-0">
                            <img
                                v-if="page.props.auth.user.avatar"
                                :src="page.props.auth.user.avatar"
                                :alt="page.props.auth.user.name"
                                class="w-12 h-12 rounded-xl object-cover ring-4"
                                :class="dark ? 'ring-[#111113]' : 'ring-white'"
                            />
                            <div
                                v-else
                                class="w-12 h-12 rounded-xl flex items-center justify-center text-[17px] font-bold select-none text-white ring-4"
                                :class="dark ? 'ring-[#111113]' : 'ring-white'"
                                :style="{ backgroundColor: viewerAccent }"
                            >
                                {{ (page.props.auth.user.name ?? '?')[0].toUpperCase() }}
                            </div>
                            <span
                                class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 border-2"
                                :class="dark ? 'border-[#111113]' : 'border-white'"
                                title="Online"
                            />
                        </div>
                        <div class="min-w-0 pb-0.5">
                            <p class="text-[14px] font-semibold truncate" :class="textPri">{{ page.props.auth.user.name }}</p>
                            <p v-if="page.props.auth.user.username" class="text-[12px] font-mono truncate" :class="textMute">
                                @{{ page.props.auth.user.username }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap mt-3">
                        <span v-if="viewer?.role" class="inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-full border"
                            :style="{ backgroundColor: viewer.role.color + '18', color: viewer.role.color, borderColor: viewer.role.color + '38' }">
                            {{ viewer.role.name }}
                        </span>
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 dark:text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                            {{ t('home.sidebar_online') }}
                        </span>
                    </div>

                    <!-- Badges -->
                    <div v-if="viewer?.achievements?.length" class="flex items-center gap-1.5 mt-3 pt-3 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                        <component :is="achievementIcons[slug] ?? Trophy" v-for="slug in viewer.achievements.slice(0, 6)" :key="slug"
                            :size="14" :stroke-width="1.8" :title="slug" :class="textMute" />
                        <span v-if="viewer.achievements.length > 6" class="text-[10px]" :class="textMute">
                            +{{ viewer.achievements.length - 6 }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 px-4 pb-4">
                    <Link
                        :href="page.props.auth.user.username
                            ? route('profile.show', { username: page.props.auth.user.username })
                            : '#'"
                        class="flex items-center justify-center gap-1.5 py-2 rounded-lg text-[12px] font-medium border transition-colors"
                        :class="btnBorder"
                    >
                        <Users :size="13" :stroke-width="1.8" />
                        {{ t('home.my_profile') }}
                    </Link>
                    <Link
                        :href="route('account.favorites')"
                        class="flex items-center justify-center gap-1.5 py-2 rounded-lg text-[12px] font-medium border transition-colors"
                        :class="btnBorder"
                    >
                        <Star :size="13" :stroke-width="1.8" />
                        {{ t('home.my_servers') }}
                    </Link>
                    <Link
                        v-if="page.props.auth.user.is_admin"
                        :href="route('admin.dashboard')"
                        class="col-span-2 flex items-center justify-center gap-1.5 py-2 rounded-lg text-[12px] font-medium bg-blue-600 text-white hover:bg-blue-500 transition-colors"
                    >
                        <ShieldCheck :size="13" :stroke-width="2" />
                        {{ t('home.admin_panel') }}
                    </Link>
                </div>
            </template>

            <!-- Guest -->
            <template v-else>
                <!-- Banner strip (matches the logged-in card) -->
                <div class="relative h-14 overflow-hidden">
                    <div class="w-full h-full"
                        style="background: linear-gradient(135deg, #3b82f655 0%, #8b5cf628 60%, transparent 100%)" />
                    <div v-if="dark" class="absolute inset-0 opacity-30"
                        style="background-image:radial-gradient(circle,rgba(255,255,255,0.05) 1px,transparent 1px);background-size:16px 16px" />
                </div>

                <div class="px-4 -mt-6 pb-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center ring-4 bg-blue-600 text-white"
                        :class="dark ? 'ring-[#111113]' : 'ring-white'">
                        <Gamepad2 :size="22" :stroke-width="1.8" />
                    </div>

                    <p class="text-[14px] font-semibold mt-2.5" :class="textPri">{{ t('home.welcome_title') }}</p>
                    <p class="text-[12.5px] leading-relaxed mt-0.5" :class="textSec">{{ t('home.welcome_desc') }}</p>

                    <!-- Perks -->
                    <ul class="flex flex-col gap-2 mt-3 pt-3 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                        <li v-for="perk in guestPerks" :key="perk.label" class="flex items-center gap-2.5 text-[12px]" :class="textSec">
                            <span class="w-6 h-6 rounded-md flex items-center justify-center shrink-0" :class="iconBg">
                                <component :is="perk.icon" :size="12" :stroke-width="1.8" :class="perk.color" />
                            </span>
                            {{ perk.label }}
                        </li>
                    </ul>

                    <div class="flex flex-col gap-2 mt-4">
                        <Link
                            :href="route('login')"
                            class="flex items-center justify-center gap-2 py-2.5 rounded-lg text-[13px] font-medium bg-blue-600 text-white hover:bg-blue-500 transition-colors"
                        >
                            <LogIn :size="14" :stroke-width="2" />
                            {{ t('home.login') }}
                        </Link>
                        <Link
                            :href="route('register')"
                            class="flex items-center justify-center py-2 rounded-lg text-[13px] border transition-colors"
                            :class="btnBorder"
                        >
                            {{ t('home.create_account') }}
                        </Link>
                    </div>

                    <p class="flex items-center justify-center gap-1.5 text-[11px] mt-3" :class="textMute">
                        <UsersRound :size="11" :stroke-width="1.8" />
                        {{ t('home.join_members', { count: stats.members.toLocaleString() }) }}
                    </p>
                </div>
            </template>
        </div>

        <!-- Live player figures used to live here as a chart. They now sit in the
             hero and the stats strip, where they get the width to be readable —
             repeating them here only pushed the useful links further down. -->

        <!-- ── Community activity ── -->
        <div v-if="communityActivity.length" class="hc-reveal rounded-xl border overflow-hidden" :class="card"
            style="animation-delay:0.06s">
            <div class="px-4 py-3 border-b" :class="cardHead">
                <div class="flex items-center gap-2">
                    <Activity :size="13" :stroke-width="1.8" :class="dark ? 'text-blue-400' : 'text-blue-500'" />
                    <p class="text-[13px] font-semibold" :class="textPri">{{ t('home.community_activity') }}</p>
                </div>
                <p class="text-[11px] mt-0.5 leading-snug" :class="textMute">{{ t('home.community_activity_hint') }}</p>
            </div>
            <div class="divide-y" :class="divider">
                <component
                    :is="item.url ? Link : 'div'"
                    v-for="(item, i) in communityActivity"
                    :key="i"
                    :href="item.url ?? undefined"
                    class="flex items-start gap-2.5 px-4 py-2.5 transition-colors"
                    :class="item.url ? rowHover : ''"
                >
                    <span class="w-6 h-6 rounded-lg overflow-hidden shrink-0 mt-0.5">
                        <img v-if="item.avatar" :src="item.avatar" class="w-full h-full object-cover" :alt="item.username ?? ''" />
                        <span v-else class="w-full h-full flex items-center justify-center text-[9px] font-black text-white uppercase bg-blue-500">
                            {{ (item.username ?? '?')[0] }}
                        </span>
                    </span>
                    <p class="flex-1 min-w-0 text-[12px] leading-snug" :class="textSec">
                        <span class="font-bold" :class="textPri">{{ item.username }}</span>
                        {{ activityLabel(item) }}
                        <span class="text-[10px]" :class="textMute"> · {{ item.at }}</span>
                    </p>
                </component>
            </div>
        </div>

        <!-- ── Quick links ── -->
        <div class="hc-reveal rounded-xl border overflow-hidden" :class="card" style="animation-delay:0.12s">
            <div class="px-4 py-3 border-b" :class="cardHead">
                <p class="text-[13px] font-semibold" :class="textPri">{{ t('home.links_title') }}</p>
                <p class="text-[11px] mt-0.5 leading-snug" :class="textMute">{{ t('home.links_hint') }}</p>
            </div>
            <div class="divide-y" :class="divider">
                <component
                    :is="link.external ? 'a' : Link"
                    v-for="link in quickLinks"
                    :key="link.label"
                    :href="link.href"
                    :target="link.external ? '_blank' : undefined"
                    :rel="link.external ? 'noopener noreferrer' : undefined"
                    class="group flex items-center gap-3 px-4 py-2.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                    :class="[textSec, rowHover]"
                >
                    <component :is="link.icon" :size="14" :stroke-width="1.75"
                        class="shrink-0 transition-colors group-hover:text-blue-500" :class="textMute" />
                    <span class="text-[13px]">{{ link.label }}</span>
                    <ArrowRight :size="12" :stroke-width="2"
                        class="ml-auto shrink-0 opacity-0 -translate-x-1 transition-all group-hover:opacity-100 group-hover:translate-x-0"
                        :class="textMute" aria-hidden="true" />
                </component>
            </div>
        </div>
    </aside>
</template>
