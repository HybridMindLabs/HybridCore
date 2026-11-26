<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity, AlertTriangle, ArrowRight, BadgeCheck, BarChart2, Gamepad2, Info, MessageSquare, MousePointerClick,
    Paintbrush, PieChart, Power, Puzzle, Server, Shapes, ShieldBan, TrendingDown, TrendingUp, UserPlus, Users, Wifi,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import Tooltip from '@/components/UI/Tooltip.vue';
import DashboardCard from '@/components/Admin/Dashboard/DashboardCard.vue';
import DashboardWidgets from '@/components/Admin/Dashboard/DashboardWidgets.vue';
import HighchartsChart from '@/components/Admin/Dashboard/HighchartsChart.vue';
import RevenuePanel from '@/components/Admin/Dashboard/RevenuePanel.vue';
import {
    activityChart, gamesChart, palette, rolesChart, userStatusChart,
    type DashboardStats, type DashboardWidget, type Revenue,
} from '@/components/Admin/Dashboard/charts';
import { confirm } from '@/composables/useConfirm';

const props = defineProps<{ widgets: DashboardWidget[]; stats?: DashboardStats; maintenance_mode?: boolean; revenue?: Revenue | null }>();

// ── Maintenance ──────────────────────────────────────────────────
async function toggleMaintenance() {
    // Turning it on takes the public site down for everyone; ask first.
    if (!props.maintenance_mode && !await confirm('Enable maintenance mode? Visitors will see a maintenance page until you turn it off.', { confirmLabel: 'Enable', danger: true })) return;
    router.post(route(props.maintenance_mode ? 'admin.maintenance.disable' : 'admin.maintenance.enable'));
}

// ── Theme (charts are drawn in JS, so they follow the class themselves) ──
const dark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
let observer: MutationObserver | null = null;
onMounted(() => {
    observer = new MutationObserver(() => { dark.value = document.documentElement.classList.contains('dark'); });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
onUnmounted(() => observer?.disconnect());

// ── KPI cards ────────────────────────────────────────────────────
const kpis = computed(() => {
    const s = props.stats;
    if (!s) return [];
    const rows = [
        { label: 'Total users', value: s.total_users, icon: Users, color: palette.blue, bg: 'rgba(59,130,246,0.08)', help: 'Every registered account, including banned and unverified ones.' },
        { label: 'New today', value: s.new_users_today, icon: UserPlus, color: palette.emerald, bg: 'rgba(16,185,129,0.08)', help: 'Accounts created since midnight, server time.' },
        { label: 'This week', value: s.new_users_week, icon: TrendingUp, color: palette.violet, bg: 'rgba(139,92,246,0.08)', help: 'Accounts created since the start of this week.' },
        { label: 'Banned', value: s.banned_users, icon: ShieldBan, color: palette.rose, bg: 'rgba(244,63,94,0.08)', help: 'Accounts currently under a ban — lifted bans are not counted.' },
        { label: 'Verified', value: s.verified_users, icon: BadgeCheck, color: palette.cyan, bg: 'rgba(6,182,212,0.08)', help: 'Accounts with a confirmed email address.' },
    ];
    if (s.servers_total !== null) rows.push({ label: 'Servers', value: s.servers_total, icon: Server, color: palette.amber, bg: 'rgba(245,158,11,0.08)', help: 'Every game server registered on the platform.' });
    if (s.servers_online !== null) rows.push({ label: 'Online', value: s.servers_online, icon: Wifi, color: palette.emerald, bg: 'rgba(16,185,129,0.08)', help: 'Servers that answered a status query on the last check.' });
    if (s.total_messages !== null) rows.push({ label: 'Messages', value: s.total_messages, icon: MessageSquare, color: palette.blue, bg: 'rgba(59,130,246,0.08)', help: 'Total private messages sent between members.' });
    return rows;
});

// Registrations this week vs the week before, from data already on the page.
const registrationsTrend = computed(() => {
    const points = props.stats?.registrations_last_30_days ?? [];
    if (points.length < 14) return null;

    const recent = points.slice(-7).reduce((sum, d) => sum + d.count, 0);
    const prior = points.slice(-14, -7).reduce((sum, d) => sum + d.count, 0);
    if (prior === 0) return recent > 0 ? { pct: 100, up: true } : null;

    const pct = Math.round(((recent - prior) / prior) * 100);
    return { pct: Math.abs(pct), up: pct >= 0 };
});

// ── Charts ───────────────────────────────────────────────────────
const activityOptions = computed(() => props.stats ? activityChart(props.stats, dark.value) : null);
const statusOptions = computed(() => props.stats ? userStatusChart(props.stats, dark.value) : null);
const rolesOptions = computed(() => rolesChart(props.stats?.role_distribution ?? [], dark.value));
const gamesOptions = computed(() => gamesChart(props.stats?.top_games ?? [], dark.value));

const engagement = computed(() => {
    const e = props.stats?.engagement;
    if (!e || [e.reviews, e.comments, e.follows].every((v) => v === null)) return [];
    return [
        { label: 'Reviews', total: e.reviews, week: e.reviews_week },
        { label: 'Comments', total: e.comments, week: e.comments_week },
        { label: 'Follows', total: e.follows, week: e.follows_week },
    ];
});

const clicked = computed(() => props.stats?.top_clicked_servers ?? []);
const topClicks = computed(() => Math.max(1, clicked.value[0]?.clicks ?? 0));

const quickLinks = [
    { label: 'Users', desc: 'Manage accounts, roles & bans', route: 'admin.users.index', icon: Users, color: palette.blue, bg: 'rgba(59,130,246,0.08)' },
    { label: 'Extensions', desc: 'Install & configure add-ons', route: 'admin.extensions.index', icon: Puzzle, color: palette.violet, bg: 'rgba(139,92,246,0.08)' },
    { label: 'Themes', desc: 'Customise the public site look', route: 'admin.themes.index', icon: Paintbrush, color: palette.amber, bg: 'rgba(245,158,11,0.08)' },
];
</script>

<template>
    <Head title="Dashboard" />
    <AdminLayout title="Dashboard">

        <ExtensionSlot name="admin.dashboard.top" />

        <div v-if="maintenance_mode" class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl border border-amber-500/30 bg-amber-500/10" role="status">
            <AlertTriangle :size="15" :stroke-width="2" class="text-amber-400 shrink-0" aria-hidden="true" />
            <p class="text-amber-300 text-[13px] font-semibold flex-1">
                Site is in <strong>maintenance mode</strong> — visitors see a maintenance page.
            </p>
            <button type="button" class="text-[12px] font-bold px-3 py-1.5 rounded-lg bg-amber-500/20 border border-amber-500/30 text-amber-300 hover:bg-amber-500/30 transition-colors"
                @click="toggleMaintenance">Disable</button>
        </div>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-zinc-100 text-xl font-black tracking-tight">Dashboard</h1>
                <p class="text-zinc-500 text-sm mt-0.5">Platform overview — live metrics &amp; activity.</p>
            </div>
            <button v-if="!maintenance_mode" type="button"
                class="flex items-center gap-1.5 text-[12px] font-semibold px-3 py-2 rounded-lg border border-zinc-800 text-zinc-500 hover:text-amber-400 hover:border-amber-500/40 transition-colors"
                @click="toggleMaintenance">
                <Power :size="13" :stroke-width="2" aria-hidden="true" /> Maintenance
            </button>
        </div>

        <!-- KPI grid -->
        <dl v-if="kpis.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 mb-6">
            <div v-for="(k, i) in kpis" :key="k.label"
                class="hc-hero-in rounded-xl border border-zinc-800/70 bg-admin-surface px-4 py-3.5 flex items-center gap-3.5 transition-[border-color,transform] duration-200 hover:border-zinc-700/70 hover:-translate-y-0.5"
                :style="{ animationDelay: `${i * 40}ms` }">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :style="{ backgroundColor: k.bg }" aria-hidden="true">
                    <component :is="k.icon" :size="16" :stroke-width="1.8" :style="{ color: k.color }" />
                </div>
                <div class="min-w-0 flex flex-col">
                    <dt class="order-2 text-[11px] font-medium text-zinc-500 mt-0.5 truncate flex items-center gap-1">
                        {{ k.label }}
                        <Tooltip :text="k.help"><Info :size="12" :stroke-width="2" class="text-zinc-500 hover:text-zinc-300 transition-colors" /></Tooltip>
                    </dt>
                    <dd class="order-1 text-[22px] font-black leading-none" :style="{ color: k.color }">{{ k.value?.toLocaleString() ?? '—' }}</dd>
                </div>
            </div>
        </dl>

        <!-- Extension and core widgets. The users count is already the first KPI above. -->
        <DashboardWidgets :widgets="widgets" :hidden="['core.users-count']" />

        <RevenuePanel v-if="revenue" :revenue="revenue" :dark="dark" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <DashboardCard class="lg:col-span-2" style="animation-delay: 120ms" title="Registrations & Logins" subtitle="Last 30 days"
                help="New account signups vs. successful logins, one point per day." :icon="Activity" tone="bg-blue-500/10 text-blue-400">
                <template #aside>
                    <span v-if="registrationsTrend" class="flex items-center gap-1 text-[11px] font-bold shrink-0"
                        :class="registrationsTrend.up ? 'text-emerald-400' : 'text-red-400'">
                        <component :is="registrationsTrend.up ? TrendingUp : TrendingDown" :size="12" :stroke-width="2.5" aria-hidden="true" />
                        {{ registrationsTrend.pct }}% vs prior week
                    </span>
                </template>
                <HighchartsChart v-if="activityOptions && stats?.registrations_last_30_days?.length" :options="activityOptions" label="Registrations and logins per day" />
                <div v-else class="flex flex-col items-center justify-center gap-2 py-16 text-center">
                    <Activity :size="22" :stroke-width="1.5" class="text-zinc-600" aria-hidden="true" />
                    <p class="text-zinc-500 text-[12px]">No signups in the last 30 days.</p>
                </div>
            </DashboardCard>

            <DashboardCard style="animation-delay: 160ms" title="User Status" subtitle="Verified / unverified / banned"
                help="Every user split into verified, unverified, or banned." :icon="BarChart2" tone="bg-violet-500/10 text-violet-400">
                <HighchartsChart v-if="statusOptions" :options="statusOptions" label="Users by status" />
            </DashboardCard>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <DashboardCard style="animation-delay: 200ms" title="Roles Distribution" subtitle="Users by primary role"
                help="Users grouped by their primary role. Users with no role assigned are excluded." :icon="PieChart" tone="bg-amber-500/10 text-amber-400">
                <HighchartsChart v-if="stats?.role_distribution?.length" :options="rolesOptions" label="Users by role" />
                <div v-else class="flex flex-col items-center justify-center gap-2 py-14 text-center">
                    <Shapes :size="22" :stroke-width="1.5" class="text-zinc-600" aria-hidden="true" />
                    <p class="text-zinc-500 text-[12px]">No roles assigned to any user yet.</p>
                </div>
            </DashboardCard>

            <DashboardCard style="animation-delay: 240ms" title="Top Games" subtitle="Servers per game"
                help="The games with the most registered servers on this platform." :icon="Server" tone="bg-emerald-500/10 text-emerald-400">
                <HighchartsChart v-if="stats?.top_games?.length" :options="gamesOptions" label="Servers per game" />
                <div v-else class="flex flex-col items-center justify-center gap-2 py-14 text-center">
                    <Gamepad2 :size="22" :stroke-width="1.5" class="text-zinc-600" aria-hidden="true" />
                    <p class="text-zinc-500 text-[12px]">No games yet — add one in Servers → Games.</p>
                </div>
            </DashboardCard>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <DashboardCard style="animation-delay: 280ms" title="Community Engagement" subtitle="Totals, with the last 7 days in green"
                help="Totals from reviews, comments and follows — only counted if that feature is enabled." :icon="Activity" tone="bg-violet-500/10 text-violet-400">
                <dl v-if="engagement.length" class="grid grid-cols-3 gap-3">
                    <div v-for="item in engagement" :key="item.label" class="flex flex-col rounded-lg border border-zinc-800/60 bg-zinc-900/40 p-3.5 text-center">
                        <dt class="order-2 text-[10px] uppercase tracking-widest text-zinc-500 mt-1.5">{{ item.label }}</dt>
                        <dd class="order-1 text-[22px] font-black text-zinc-100 tabular-nums leading-none">{{ item.total ?? '—' }}</dd>
                        <dd v-if="item.week" class="order-3 text-[11px] font-semibold text-emerald-400 mt-1">+{{ item.week }} this week</dd>
                    </div>
                </dl>
                <div v-else class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <Activity :size="22" :stroke-width="1.5" class="text-zinc-600" aria-hidden="true" />
                    <p class="text-zinc-500 text-[12px]">No engagement data — reviews, comments and follows aren't enabled yet.</p>
                </div>
            </DashboardCard>

            <DashboardCard style="animation-delay: 320ms" title="Most Clicked Servers" subtitle="Connect clicks, last 30 days"
                help="Servers ranked by how many times visitors clicked 'Connect' on the public site." :icon="MousePointerClick" tone="bg-amber-500/10 text-amber-400">
                <ol v-if="clicked.length" class="flex flex-col gap-2">
                    <li v-for="(srv, i) in clicked" :key="srv.name" class="flex items-center gap-3">
                        <span class="text-[11px] font-black tabular-nums w-5 text-zinc-500">{{ i + 1 }}.</span>
                        <span class="text-[13px] text-zinc-300 truncate flex-1">{{ srv.name }}</span>
                        <span class="w-24 h-1.5 rounded-full bg-zinc-800 overflow-hidden shrink-0" aria-hidden="true">
                            <span class="block h-full rounded-full bg-amber-400" :style="{ width: Math.round((srv.clicks / topClicks) * 100) + '%' }" />
                        </span>
                        <span class="text-[12px] font-bold tabular-nums text-zinc-400 w-10 text-right shrink-0">{{ srv.clicks }}</span>
                    </li>
                </ol>
                <div v-else class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <MousePointerClick :size="22" :stroke-width="1.5" class="text-zinc-600" aria-hidden="true" />
                    <p class="text-zinc-500 text-[12px]">No connect clicks recorded yet.</p>
                </div>
            </DashboardCard>
        </div>

        <nav class="hc-hero-in mb-2" style="animation-delay: 360ms" aria-label="Quick links">
            <h2 class="text-zinc-400 text-[11px] uppercase tracking-widest font-bold mb-2">Quick links</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Link v-for="link in quickLinks" :key="link.route" :href="route(link.route)"
                    class="group flex items-center gap-3 rounded-xl border border-zinc-800/70 bg-admin-surface p-4 hover:border-zinc-700/70 transition-colors">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :style="{ backgroundColor: link.bg }" aria-hidden="true">
                        <component :is="link.icon" :size="16" :stroke-width="1.75" :style="{ color: link.color }" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-zinc-100 text-[13px] font-semibold">{{ link.label }}</p>
                        <p class="text-zinc-500 text-[11px] truncate">{{ link.desc }}</p>
                    </div>
                    <ArrowRight :size="14" :stroke-width="2" class="text-zinc-600 group-hover:text-blue-400 group-hover:translate-x-0.5 transition-all shrink-0" aria-hidden="true" />
                </Link>
            </div>
        </nav>

        <ExtensionSlot name="admin.dashboard.bottom" />

    </AdminLayout>
</template>
