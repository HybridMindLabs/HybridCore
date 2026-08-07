<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft, ShieldAlert, Ban, Pencil, MessageSquare, Star, Users, UserPlus,
    Trophy, Heart, Flag, FileWarning, Activity, Globe, StickyNote, Trash2,
    CheckCircle2, XCircle, Link2, Calendar, Clock, Hash, MapPin, VenetianMask, LockKeyholeOpen,
    Monitor, Smartphone, LogOut,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import EmptyState from '@/components/UI/EmptyState.vue';
import { ROLE_ICONS } from '@/constants/icons';

interface RoleInfo { name: string; slug: string; color: string; icon: string }

const props = defineProps<{
    user: {
        id: number; name: string; username: string | null; email: string;
        avatar: string | null; banner: string | null; bio: string | null; location: string | null;
        is_admin: boolean; banned: boolean; locked_out: boolean; verified: boolean; online: boolean;
        role: RoleInfo | null; roles: RoleInfo[];
        created_at: string | null; last_seen_at: string | null;
    };
    stats: {
        comments: number; reviews: number; followers: number; following: number;
        achievements: number; favourites: number; reports_against: number; reports_filed: number;
    };
    connectedAccounts: { provider: string; created_at: string | null }[];
    achievements: { slug: string; earned_at: string | null }[];
    recentComments: { id: number; body: string; article_title: string | null; article_slug: string | null; created_at: string }[];
    recentReviews: { id: number; rating: number; body: string; server_name: string | null; created_at: string }[];
    recentActivity: { event: string; description: string | null; created_at: string }[];
    loginHistory: { ip: string; country: string | null; city: string | null; user_agent: string; created_at: string }[];
    notes: { id: number; body: string; author: string; created_at: string }[];
    sessions: { id: string; ip_address: string | null; last_activity: number; device: { browser: string; os: string; mobile: boolean } }[];
}>();

const tabs = [
    { key: 'activity', label: 'Activity', icon: Activity },
    { key: 'content',  label: 'Content',  icon: MessageSquare },
    { key: 'logins',   label: 'Logins',   icon: Globe },
    { key: 'sessions', label: 'Sessions', icon: Monitor },
] as const;

const activeTab = ref<typeof tabs[number]['key']>('activity');

const tabCounts = computed(() => ({
    activity: props.recentActivity.length,
    content: props.recentComments.length + props.recentReviews.length,
    logins: props.loginHistory.length,
    sessions: props.sessions.length,
}));

const initials = computed(() =>
    props.user.name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('') || '?',
);

const statTiles = computed(() => [
    { label: 'Comments',        value: props.stats.comments,        icon: MessageSquare, color: 'text-blue-400' },
    { label: 'Reviews',         value: props.stats.reviews,         icon: Star,          color: 'text-amber-400' },
    { label: 'Followers',       value: props.stats.followers,       icon: Users,         color: 'text-violet-400' },
    { label: 'Following',       value: props.stats.following,       icon: UserPlus,      color: 'text-violet-400' },
    { label: 'Achievements',    value: props.stats.achievements,    icon: Trophy,        color: 'text-emerald-400' },
    { label: 'Favourites',      value: props.stats.favourites,      icon: Heart,         color: 'text-rose-400' },
    { label: 'Reports against', value: props.stats.reports_against, icon: FileWarning,   color: props.stats.reports_against > 0 ? 'text-red-400' : 'text-zinc-500' },
    { label: 'Reports filed',   value: props.stats.reports_filed,   icon: Flag,          color: 'text-zinc-400' },
]);

const noteForm = useForm({ body: '' });

function addNote() {
    noteForm.post(route('admin.users.notes.store', props.user.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

function deleteNote(id: number) {
    if (!confirm('Delete this note?')) return;
    router.delete(route('admin.users.notes.destroy', [props.user.id, id]), { preserveScroll: true });
}

const canImpersonate = computed(() => !props.user.is_admin && !props.user.banned);

function impersonate() {
    if (!confirm(`Browse the site as "${props.user.name}"? You will be signed in as them until you click "Return to my account".`)) return;
    router.post(route('admin.users.impersonate', props.user.id));
}

function unlock() {
    router.post(route('admin.users.unlock', props.user.id), {}, { preserveScroll: true });
}

const revokingSession = ref<string | null>(null);

function revokeSession(sessionId: string) {
    if (!confirm('Revoke this session? The device will be signed out immediately.')) return;
    revokingSession.value = sessionId;
    router.delete(route('admin.users.sessions.destroy', [props.user.id, sessionId]), {
        preserveScroll: true,
        onFinish: () => (revokingSession.value = null),
    });
}

function formatLastActivity(ts: number): string {
    const diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}
</script>

<template>
    <Head :title="`User — ${user.name}`" />
    <AdminLayout title="User Details">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm">
            <Link :href="route('admin.users.index')" class="flex items-center gap-1.5 text-zinc-500 hover:text-zinc-100 transition-colors">
                <ChevronLeft :size="13" :stroke-width="1.75" /> Users
            </Link>
            <span class="text-zinc-700">/</span>
            <span class="text-zinc-300">{{ user.name }}</span>
            <span class="text-zinc-700 font-mono text-xs ml-1">#{{ user.id }}</span>
            <div class="ml-auto flex items-center gap-2">
                <button
                    v-if="user.locked_out"
                    type="button"
                    class="flex items-center gap-1.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg px-3 py-1.5 text-xs font-semibold hover:bg-emerald-500/20 transition-colors"
                    @click="unlock"
                >
                    <LockKeyholeOpen :size="12" :stroke-width="1.75" /> Unlock account
                </button>
                <button
                    v-if="canImpersonate"
                    type="button"
                    class="flex items-center gap-1.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-lg px-3 py-1.5 text-xs font-semibold hover:bg-amber-500/20 transition-colors"
                    @click="impersonate"
                >
                    <VenetianMask :size="12" :stroke-width="1.75" /> Login as user
                </button>
                <Link
                    :href="route('admin.users.edit', user.id)"
                    class="flex items-center gap-1.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-lg px-3 py-1.5 text-xs font-semibold hover:bg-blue-500/20 transition-colors"
                >
                    <Pencil :size="12" :stroke-width="1.75" /> Edit user
                </Link>
            </div>
        </div>

        <!-- Header card -->
        <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden mb-5">
            <div class="h-24 bg-gradient-to-r from-blue-500/15 via-violet-500/10 to-transparent" :style="user.banner ? { backgroundImage: `url(${user.banner})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}" />
            <div class="px-6 pb-5 -mt-8 flex flex-wrap items-end gap-4">
                <div class="relative">
                    <div class="w-16 h-16 rounded-2xl overflow-hidden border-2 border-[#111113] bg-zinc-800">
                        <img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-lg font-bold text-zinc-400">{{ initials }}</div>
                    </div>
                    <span v-if="user.online" class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-emerald-400 border-2 border-[#111113]" title="Online now" />
                </div>
                <div class="flex-1 min-w-0 pb-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-zinc-100 text-lg font-bold truncate">{{ user.name }}</h1>
                        <span
                            v-for="role in user.roles"
                            :key="role.slug"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :style="{ background: role.color + '22', color: role.color }"
                        >
                            <component :is="ROLE_ICONS[role.icon]" :size="10" :stroke-width="2" />
                            {{ role.name }}
                        </span>
                        <span v-if="user.is_admin" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium bg-blue-500/15 text-blue-400">
                            <ShieldAlert :size="10" :stroke-width="2" /> Admin
                        </span>
                        <span v-if="user.banned" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium bg-red-500/15 text-red-400">
                            <Ban :size="10" :stroke-width="2" /> Banned
                        </span>
                        <span v-if="user.locked_out" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium bg-amber-500/15 text-amber-400">
                            <ShieldAlert :size="10" :stroke-width="2" /> Locked out
                        </span>
                    </div>
                    <p class="text-zinc-500 text-xs mt-0.5">
                        <span v-if="user.username">@{{ user.username }} · </span>{{ user.email }}
                    </p>
                    <p v-if="user.bio" class="text-zinc-400 text-xs mt-1 line-clamp-2">{{ user.bio }}</p>
                </div>
            </div>
        </div>

        <!-- Stat tiles -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-5">
            <div
                v-for="(tile, i) in statTiles"
                :key="tile.label"
                class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl px-3 py-3 flex flex-col gap-1"
                :style="{ animationDelay: `${60 + i * 30}ms` }"
            >
                <component :is="tile.icon" :size="14" :stroke-width="1.75" :class="tile.color" />
                <span class="text-zinc-100 text-lg font-bold leading-none">{{ tile.value }}</span>
                <span class="text-zinc-600 text-[11px] leading-tight">{{ tile.label }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            <!-- ── Main column: tabs ─────────────────────────────── -->
            <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden min-w-0" style="animation-delay: 200ms">
                <div class="flex items-center gap-0.5 px-4 pt-3 border-b border-zinc-800/70">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="relative flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors rounded-t-lg"
                        :class="activeTab === tab.key ? 'text-zinc-100' : 'text-zinc-500 hover:text-zinc-300'"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" :size="14" :stroke-width="1.75" />
                        {{ tab.label }}
                        <span v-if="tabCounts[tab.key] > 0" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-zinc-800 text-zinc-400 leading-none">{{ tabCounts[tab.key] }}</span>
                        <span v-if="activeTab === tab.key" class="absolute left-0 right-0 -bottom-px h-[2px] bg-blue-500 rounded-full" />
                    </button>
                </div>

                <div class="p-5">

                    <!-- Activity tab -->
                    <div v-show="activeTab === 'activity'">
                        <div v-if="recentActivity.length" class="flex flex-col">
                            <div
                                v-for="(entry, i) in recentActivity"
                                :key="i"
                                class="flex items-start gap-3 py-2.5"
                                :class="i > 0 ? 'border-t border-zinc-800/50' : ''"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500/70 mt-1.5 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-zinc-300 text-sm truncate">{{ entry.description ?? entry.event }}</p>
                                    <p class="text-zinc-600 text-xs font-mono">{{ entry.event }}</p>
                                </div>
                                <span class="text-zinc-600 text-xs whitespace-nowrap">{{ entry.created_at }}</span>
                            </div>
                        </div>
                        <EmptyState v-else :icon="Activity" title="No activity yet" description="Actions this user takes will show up here." />
                    </div>

                    <!-- Content tab -->
                    <div v-show="activeTab === 'content'">
                        <template v-if="recentComments.length || recentReviews.length">
                            <template v-if="recentComments.length">
                                <h3 class="text-zinc-400 text-xs font-semibold uppercase tracking-wider mb-2">Recent comments</h3>
                                <div class="flex flex-col mb-5">
                                    <div
                                        v-for="(comment, i) in recentComments"
                                        :key="comment.id"
                                        class="py-2.5"
                                        :class="i > 0 ? 'border-t border-zinc-800/50' : ''"
                                    >
                                        <p class="text-zinc-300 text-sm">{{ comment.body }}</p>
                                        <p class="text-zinc-600 text-xs mt-0.5">
                                            on <span class="text-zinc-500">{{ comment.article_title ?? 'deleted article' }}</span> · {{ comment.created_at }}
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <template v-if="recentReviews.length">
                                <h3 class="text-zinc-400 text-xs font-semibold uppercase tracking-wider mb-2">Recent reviews</h3>
                                <div class="flex flex-col">
                                    <div
                                        v-for="(review, i) in recentReviews"
                                        :key="review.id"
                                        class="py-2.5"
                                        :class="i > 0 ? 'border-t border-zinc-800/50' : ''"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <span class="flex items-center gap-0.5 text-amber-400 text-xs font-semibold">
                                                <Star :size="11" :stroke-width="2" fill="currentColor" /> {{ review.rating }}/5
                                            </span>
                                            <span class="text-zinc-500 text-xs">{{ review.server_name ?? 'deleted server' }}</span>
                                            <span class="text-zinc-700 text-xs ml-auto">{{ review.created_at }}</span>
                                        </div>
                                        <p v-if="review.body" class="text-zinc-300 text-sm mt-1">{{ review.body }}</p>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <EmptyState v-else :icon="MessageSquare" title="No content yet" description="Comments and reviews this user posts will show up here." />
                    </div>

                    <!-- Logins tab -->
                    <div v-show="activeTab === 'logins'">
                        <div v-if="loginHistory.length" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-zinc-500 text-xs uppercase tracking-wider text-left">
                                        <th class="pb-2 font-semibold">IP</th>
                                        <th class="pb-2 font-semibold">Location</th>
                                        <th class="pb-2 font-semibold hidden md:table-cell">Device</th>
                                        <th class="pb-2 font-semibold text-right">When</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(login, i) in loginHistory" :key="i" class="border-t border-zinc-800/50">
                                        <td class="py-2 font-mono text-xs text-zinc-300">{{ login.ip }}</td>
                                        <td class="py-2 text-zinc-400 text-xs">
                                            <span class="inline-flex items-center gap-1">
                                                <MapPin :size="11" :stroke-width="1.75" class="text-zinc-600" />
                                                {{ [login.city, login.country].filter(Boolean).join(', ') || 'Unknown' }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-zinc-500 text-xs hidden md:table-cell truncate max-w-[240px]">{{ login.user_agent }}</td>
                                        <td class="py-2 text-zinc-500 text-xs text-right whitespace-nowrap">{{ login.created_at }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <EmptyState v-else :icon="Globe" title="No login history" description="Sign-ins from this account will be recorded here." />
                    </div>

                    <!-- Sessions tab -->
                    <div v-show="activeTab === 'sessions'">
                        <div v-if="sessions.length" class="flex flex-col gap-2">
                            <div
                                v-for="session in sessions"
                                :key="session.id"
                                class="flex items-center gap-3 rounded-lg border border-zinc-800/60 bg-zinc-900/40 px-3 py-2.5"
                            >
                                <div class="shrink-0 w-8 h-8 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                                    <component :is="session.device.mobile ? Smartphone : Monitor" :size="13" :stroke-width="1.8" class="text-blue-400" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-zinc-300 text-sm font-medium">{{ session.device.browser }} on {{ session.device.os }}</p>
                                    <p class="text-zinc-600 text-xs">{{ session.ip_address ?? 'Unknown IP' }} · {{ formatLastActivity(session.last_activity) }}</p>
                                </div>
                                <button
                                    type="button"
                                    :disabled="revokingSession !== null"
                                    class="flex items-center gap-1.5 shrink-0 text-zinc-500 hover:text-red-400 text-xs font-semibold transition-colors disabled:opacity-50"
                                    @click="revokeSession(session.id)"
                                >
                                    <LogOut :size="12" :stroke-width="1.75" />
                                    {{ revokingSession === session.id ? 'Revoking…' : 'Revoke' }}
                                </button>
                            </div>
                        </div>
                        <EmptyState v-else :icon="Monitor" title="No active sessions" description="This user isn't currently signed in anywhere." />
                    </div>

                </div>
            </div>

            <!-- ── Sidebar ───────────────────────────────────────── -->
            <div class="hc-hero-in flex flex-col gap-4" style="animation-delay: 260ms">

                <!-- Admin notes -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-1 flex items-center gap-1.5">
                        <StickyNote :size="14" :stroke-width="1.75" class="text-amber-400" /> Admin notes
                    </h3>
                    <p class="text-zinc-600 text-xs mb-3 leading-relaxed">Internal — visible only to the admin team.</p>

                    <form class="mb-4" @submit.prevent="addNote">
                        <textarea
                            v-model="noteForm.body"
                            rows="2"
                            placeholder="Add a note about this user…"
                            class="w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 resize-none"
                        />
                        <p v-if="noteForm.errors.body" class="text-red-400 text-xs mt-1">{{ noteForm.errors.body }}</p>
                        <button
                            type="submit"
                            :disabled="noteForm.processing || !noteForm.body.trim()"
                            class="mt-2 bg-blue-500 text-white font-semibold rounded-lg px-4 py-1.5 text-xs hover:bg-blue-400 transition-colors disabled:opacity-50"
                        >
                            {{ noteForm.processing ? 'Saving…' : 'Add note' }}
                        </button>
                    </form>

                    <div v-if="notes.length" class="flex flex-col gap-3">
                        <div v-for="note in notes" :key="note.id" class="bg-zinc-900/40 border border-zinc-800/50 rounded-lg p-3 group">
                            <p class="text-zinc-300 text-sm whitespace-pre-wrap break-words">{{ note.body }}</p>
                            <div class="flex items-center gap-2 mt-2 text-[11px] text-zinc-600">
                                <span class="font-medium text-zinc-500">{{ note.author }}</span>
                                <span>·</span>
                                <span>{{ note.created_at }}</span>
                                <button
                                    type="button"
                                    class="ml-auto text-zinc-700 hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100"
                                    title="Delete note"
                                    @click="deleteNote(note.id)"
                                >
                                    <Trash2 :size="12" :stroke-width="1.75" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-zinc-600 text-xs">No notes yet.</p>
                </div>

                <!-- Account meta -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-3">Account</h3>
                    <div class="flex flex-col gap-2 text-xs">
                        <div class="flex items-center gap-2 text-zinc-500">
                            <Hash :size="12" :stroke-width="1.75" class="text-zinc-700 shrink-0" />
                            <span class="text-zinc-600">User ID</span>
                            <span class="ml-auto font-mono text-zinc-400">{{ user.id }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <Calendar :size="12" :stroke-width="1.75" class="text-zinc-700 shrink-0" />
                            <span class="text-zinc-600">Joined</span>
                            <span class="ml-auto text-zinc-400 text-right">{{ user.created_at ?? '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <Clock :size="12" :stroke-width="1.75" class="text-zinc-700 shrink-0" />
                            <span class="text-zinc-600">Last seen</span>
                            <span class="ml-auto text-zinc-400">{{ user.last_seen_at ?? 'Never' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <CheckCircle2 v-if="user.verified" :size="12" :stroke-width="1.75" class="text-emerald-400 shrink-0" />
                            <XCircle v-else :size="12" :stroke-width="1.75" class="text-amber-400 shrink-0" />
                            <span class="text-zinc-600">Email</span>
                            <span class="ml-auto" :class="user.verified ? 'text-emerald-400' : 'text-amber-400'">
                                {{ user.verified ? 'Verified' : 'Not verified' }}
                            </span>
                        </div>
                        <div v-if="user.location" class="flex items-center gap-2 text-zinc-500">
                            <MapPin :size="12" :stroke-width="1.75" class="text-zinc-700 shrink-0" />
                            <span class="text-zinc-600">Location</span>
                            <span class="ml-auto text-zinc-400">{{ user.location }}</span>
                        </div>
                    </div>

                    <div v-if="connectedAccounts.length" class="mt-3 pt-3 border-t border-zinc-800/50">
                        <h4 class="text-zinc-500 text-[11px] font-semibold uppercase tracking-wider mb-2">Connected accounts</h4>
                        <div class="flex flex-col gap-1.5">
                            <div v-for="account in connectedAccounts" :key="account.provider" class="flex items-center gap-2 text-xs">
                                <Link2 :size="12" :stroke-width="1.75" class="text-zinc-600" />
                                <span class="text-zinc-300 capitalize">{{ account.provider }}</span>
                                <span class="ml-auto text-zinc-600">{{ account.created_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Achievements -->
                <div v-if="achievements.length" class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-3 flex items-center gap-1.5">
                        <Trophy :size="14" :stroke-width="1.75" class="text-emerald-400" /> Achievements
                    </h3>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="achievement in achievements"
                            :key="achievement.slug"
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium bg-zinc-800/70 text-zinc-300"
                            :title="`Earned ${achievement.earned_at}`"
                        >
                            {{ achievement.slug.replace(/_/g, ' ') }}
                        </span>
                    </div>
                </div>

            </div>
        </div>

    </AdminLayout>
</template>
