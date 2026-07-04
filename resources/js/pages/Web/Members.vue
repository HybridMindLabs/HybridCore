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
    <Head :title="t('members.title')" />

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

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-12 sm:py-16">
                <Breadcrumb :items="[{ label: 'Home', href: route('home') }, { label: t('members.title') }]" />

                <div class="max-w-2xl">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest mb-6"
                        :class="dark ? 'border-emerald-500/25 bg-emerald-500/8 text-emerald-400' : 'border-emerald-400/30 bg-emerald-50 text-emerald-600'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                        {{ t('members.online_now', { count: liveOnlineCount }) }}
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('members.hero_prefix') }}
                        <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">{{ t('members.title') }}</span>
                    </h1>

                    <p class="mt-4 text-[15px] leading-relaxed max-w-lg"
                       :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                        {{ t('members.subtitle').replace(':count', total.toLocaleString()) }}
                    </p>

                    <!-- Search -->
                    <div class="relative w-full max-w-sm mt-8">
                        <Search :size="14" :stroke-width="1.75" class="absolute left-3.5 top-1/2 -translate-y-1/2" :class="dark ? 'text-zinc-600' : 'text-zinc-400'" />
                        <input
                            v-model="search" type="text"
                            :placeholder="t('members.search_placeholder')"
                            class="w-full rounded-xl border pl-10 pr-9 py-3 text-[14px] font-medium transition focus:outline-none focus:ring-2"
                            :class="dark
                                ? 'border-zinc-800 bg-zinc-900/60 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50 focus:ring-blue-500/10'
                                : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400/60 focus:ring-blue-500/10'"
                        />
                        <button v-if="search" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 transition-colors"
                            :class="dark ? 'text-zinc-600 hover:text-zinc-400' : 'text-zinc-400 hover:text-zinc-600'"
                            @click="search = ''">
                            <X :size="13" :stroke-width="1.75" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
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

                        <!-- Badges -->
                        <div v-if="member.achievements.length > 0" class="flex items-center gap-1.5">
                            <component :is="achievementIcons[slug] ?? BadgeCheck" v-for="slug in member.achievements.slice(0, 5)" :key="slug"
                                :size="13" :stroke-width="1.8" :title="slug" :class="dark ? 'text-zinc-500' : 'text-zinc-400'" />
                            <span v-if="member.achievements.length > 5" class="text-[10px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                +{{ member.achievements.length - 5 }}
                            </span>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center gap-3 text-[11px] mt-auto pt-2.5 border-t"
                            :class="dark ? 'border-zinc-800/60 text-zinc-600' : 'border-zinc-100 text-zinc-400'">
                            <span v-if="member.location" class="flex items-center gap-1 truncate">
                                <MapPin :size="10" :stroke-width="1.75" />{{ member.location }}
                            </span>
                            <span class="ml-auto shrink-0">{{ t('members.joined') }} {{ member.joined_at }}</span>
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
