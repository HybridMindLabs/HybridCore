<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { HeartPulse, CheckCircle2, AlertTriangle, XCircle, Eraser, Construction, Cpu, HardDrive, X, RefreshCw, Info } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import Tooltip from '@/components/UI/Tooltip.vue';
import { computed, ref } from 'vue';

interface Check { label: string; category: string; status: 'ok' | 'warn' | 'fail'; detail: string }
interface Info { php: string; laravel: string; environment: string }
interface Maintenance { enabled: boolean; message: string }

const props = defineProps<{ checks: Check[]; checkedAt: string; info: Info; maintenance: Maintenance }>();

// Short "why this matters" text per check — keyed by label, mirrors the
// event-metadata pattern used on the Activity Log page.
const EXPLANATIONS: Record<string, string> = {
    'PHP Version': 'The PHP runtime version the app is executing on. Older versions miss security fixes and language features this codebase relies on.',
    'Laravel Version': 'The framework version currently installed.',
    'PHP Extensions': 'Native PHP modules the app requires for database access, string handling, and encryption. Missing ones cause hard crashes, not degraded behavior.',
    'APP_KEY': 'The encryption key used for sessions, cookies, and encrypted data. Without it, logins and encrypted fields break.',
    'Database Connection': 'Whether the app can currently reach its configured database. Nothing works without this.',
    'Storage Writable': 'The storage/ directory needs to be writable for logs, uploads, and cached views.',
    'Bootstrap Cache Writable': 'Laravel writes compiled config and route caches here — if it can\'t, performance optimizations silently fail to apply.',
    'Cache Writable': 'Confirms the configured cache driver actually accepts writes, not just that it\'s configured.',
    'Session Driver': 'Where login sessions are stored (file, database, redis, etc).',
    'Queue Driver': 'Where background jobs (emails, webhooks, backups) are dispatched to for processing.',
    'Scheduler': 'The cron-driven process that runs Laravel\'s scheduled tasks every minute. If it stops, backups, cleanups, and other timed jobs silently stop running.',
    'Queue Worker': 'The background process that actually executes queued jobs. If it stops, emails and other async work pile up unsent.',
    'Reverb (Websockets)': 'The websocket server powering live notifications, presence, and real-time server stats.',
    'Mail Configuration': 'Whether outbound email is configured. Password resets, notifications, and contact replies depend on this.',
    'Installation Lock': 'Prevents the installer from running again. If missing, anyone can re-run setup on a live site.',
    'Environment': 'Whether this instance is running in production or a lower environment (local/staging).',
    'Debug Mode': 'When enabled, errors expose stack traces and environment details to visitors — a real information leak in production.',
};

const CATEGORY_ORDER = ['Runtime', 'Storage & Data', 'Background Services', 'Environment & Security'];
const groupedChecks = computed(() => {
    const groups = new Map<string, Check[]>();
    for (const check of props.checks) {
        if (!groups.has(check.category)) groups.set(check.category, []);
        groups.get(check.category)!.push(check);
    }
    return CATEGORY_ORDER
        .filter((cat) => groups.has(cat))
        .map((cat) => ({ category: cat, items: groups.get(cat)! }));
});

const refreshing = ref(false);
function refresh() {
    refreshing.value = true;
    router.reload({ only: ['checks', 'checkedAt', 'info', 'maintenance'], onFinish: () => { refreshing.value = false; } });
}

function formatCheckedAt(iso: string): string {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

const DEFAULT_MAINTENANCE_MSG = "We're performing scheduled maintenance. We'll be back very soon!";

const statusMeta = {
    ok:   { icon: CheckCircle2,  label: 'OK',   class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
    warn: { icon: AlertTriangle, label: 'WARN',  class: 'bg-amber-500/10  text-amber-400  border-amber-500/20'  },
    fail: { icon: XCircle,       label: 'FAIL',  class: 'bg-red-500/10    text-red-400    border-red-500/20'    },
};

const summary = computed(() => ({
    ok:   props.checks.filter(c => c.status === 'ok').length,
    warn: props.checks.filter(c => c.status === 'warn').length,
    fail: props.checks.filter(c => c.status === 'fail').length,
}));

const overallStatus = computed(() => {
    if (summary.value.fail > 0) return 'fail';
    if (summary.value.warn > 0) return 'warn';
    return 'ok';
});

const cacheActions = [
    { label: 'Clear App Cache',    route: 'admin.system-health.clear-cache'   },
    { label: 'Clear Config Cache', route: 'admin.system-health.clear-config'  },
    { label: 'Clear Route Cache',  route: 'admin.system-health.clear-routes'  },
];

function runAction(routeName: string, label: string) {
    if (confirm(`Run "${label}"?`)) {
        router.post(route(routeName));
    }
}

// Maintenance modal
const showModal = ref(false);
const maintenanceForm = useForm({ message: '' });

function openEnableModal() {
    maintenanceForm.message = props.maintenance.message || DEFAULT_MAINTENANCE_MSG;
    showModal.value = true;
}

function confirmEnable() {
    if (!maintenanceForm.message.trim()) {
        maintenanceForm.message = DEFAULT_MAINTENANCE_MSG;
    }
    maintenanceForm.post(route('admin.maintenance.enable'), {
        onSuccess: () => { showModal.value = false; },
    });
}

function disableMaintenance() {
    if (confirm('Disable maintenance mode and restore public access?')) {
        router.post(route('admin.maintenance.disable'));
    }
}

const inputClass = 'w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 resize-none';
</script>

<template>
    <Head title="System Health" />
    <AdminLayout title="System Health">

        <PageHeader title="System Health" description="Platform diagnostics and maintenance tools." :icon="HeartPulse">
            <template #actions>
                <span class="text-xs text-zinc-600 hidden sm:inline">Checked {{ formatCheckedAt(checkedAt) }}</span>
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800/70 bg-zinc-900/60 text-zinc-400 text-xs font-semibold hover:text-zinc-100 hover:border-zinc-700 transition-colors disabled:opacity-50"
                    :disabled="refreshing"
                    @click="refresh"
                >
                    <RefreshCw :size="12" :stroke-width="2" :class="refreshing ? 'animate-spin' : ''" /> {{ refreshing ? 'Refreshing…' : 'Refresh' }}
                </button>
                <a
                    href="/pulse"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-violet-500/30 bg-violet-500/10 text-violet-400 text-xs font-semibold hover:bg-violet-500/20 transition-colors"
                    title="Live application monitoring: exceptions, slow queries, slow requests, queues"
                >
                    <HeartPulse :size="12" :stroke-width="2" /> Open Pulse ↗
                </a>
                <a
                    href="/horizon"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-500/30 bg-blue-500/10 text-blue-400 text-xs font-semibold hover:bg-blue-500/20 transition-colors"
                    title="Queue dashboard: workers, throughput, failed jobs with retry"
                >
                    <HeartPulse :size="12" :stroke-width="2" /> Open Horizon ↗
                </a>
                <div
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold"
                    :class="{
                        'bg-emerald-500/10 border-emerald-500/25 text-emerald-400': overallStatus === 'ok',
                        'bg-amber-500/10  border-amber-500/25  text-amber-400':  overallStatus === 'warn',
                        'bg-red-500/10    border-red-500/25    text-red-400':    overallStatus === 'fail',
                    }"
                >
                    <component
                        :is="statusMeta[overallStatus].icon"
                        :size="12" :stroke-width="2"
                    />
                    {{ overallStatus === 'ok' ? 'All systems OK' : overallStatus === 'warn' ? 'Warnings detected' : 'Failures detected' }}
                </div>
            </template>
        </PageHeader>

        <!-- Summary bar -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="hc-hero-in bg-emerald-500/5 border border-emerald-500/15 rounded-xl px-4 py-3 flex items-center gap-3" style="animation-delay: 0ms">
                <CheckCircle2 :size="18" :stroke-width="1.75" class="text-emerald-400 shrink-0" />
                <div>
                    <p class="text-2xl font-bold text-emerald-400 leading-none tabular-nums">{{ summary.ok }}</p>
                    <p class="text-xs text-zinc-500 mt-0.5">Passing</p>
                </div>
            </div>
            <div class="hc-hero-in bg-amber-500/5 border border-amber-500/15 rounded-xl px-4 py-3 flex items-center gap-3" style="animation-delay: 40ms">
                <AlertTriangle :size="18" :stroke-width="1.75" class="text-amber-400 shrink-0" />
                <div>
                    <p class="text-2xl font-bold text-amber-400 leading-none tabular-nums">{{ summary.warn }}</p>
                    <p class="text-xs text-zinc-500 mt-0.5">Warnings</p>
                </div>
            </div>
            <div class="hc-hero-in bg-red-500/5 border border-red-500/15 rounded-xl px-4 py-3 flex items-center gap-3" style="animation-delay: 80ms">
                <XCircle :size="18" :stroke-width="1.75" class="text-red-400 shrink-0" />
                <div>
                    <p class="text-2xl font-bold text-red-400 leading-none tabular-nums">{{ summary.fail }}</p>
                    <p class="text-xs text-zinc-500 mt-0.5">Failing</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_280px] gap-4 items-start">

            <!-- Checks list -->
            <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden" style="animation-delay: 120ms">
                <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                    <HeartPulse :size="13" :stroke-width="1.75" class="text-zinc-600" />
                    <span class="text-sm font-semibold text-zinc-100">Diagnostics</span>
                    <span class="ml-auto text-xs text-zinc-600">{{ checks.length }} checks</span>
                </div>

                <div v-for="group in groupedChecks" :key="group.category">
                    <div class="px-4 py-1.5 bg-[#1a1a1e] text-[10px] font-bold uppercase tracking-wider text-zinc-600">
                        {{ group.category }}
                    </div>
                    <div
                        v-for="check in group.items"
                        :key="check.label"
                        class="flex items-center justify-between px-4 py-3 border-b border-zinc-800/40 last:border-0 hover:bg-zinc-900/30 transition-colors"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[10px] font-bold shrink-0"
                                :class="statusMeta[check.status].class"
                            >
                                <component :is="statusMeta[check.status].icon" :size="10" :stroke-width="2.5" />
                                {{ statusMeta[check.status].label }}
                            </span>
                            <span class="text-zinc-200 text-sm">{{ check.label }}</span>
                            <Tooltip v-if="EXPLANATIONS[check.label]" :text="EXPLANATIONS[check.label]">
                                <Info :size="11" :stroke-width="2" class="text-zinc-700 hover:text-zinc-400 transition-colors" />
                            </Tooltip>
                        </div>
                        <span :title="check.detail" class="text-zinc-500 text-xs text-right ml-4 shrink-0 max-w-[45%] truncate font-mono">{{ check.detail }}</span>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="flex flex-col gap-4">

                <!-- Environment info -->
                <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden" style="animation-delay: 160ms">
                    <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <Cpu :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Environment</span>
                    </div>
                    <div class="divide-y divide-zinc-800/40">
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500">PHP</span>
                            <span class="text-xs font-mono text-zinc-300">{{ info.php }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500">Laravel</span>
                            <span class="text-xs font-mono text-zinc-300">{{ info.laravel }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500">Environment</span>
                            <span
                                class="text-[10px] font-bold px-2 py-0.5 rounded border"
                                :class="info.environment === 'production'
                                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                    : 'bg-amber-500/10 text-amber-400 border-amber-500/20'"
                            >{{ info.environment }}</span>
                        </div>
                    </div>
                </div>

                <!-- Maintenance mode -->
                <div class="hc-hero-in bg-[#111113] border rounded-xl overflow-hidden" style="animation-delay: 200ms" :class="maintenance.enabled ? 'border-amber-500/30' : 'border-zinc-800/70'">
                    <div class="px-4 py-3 border-b flex items-center gap-2" :class="maintenance.enabled ? 'border-amber-500/20 bg-amber-500/5' : 'border-zinc-800/70'">
                        <Construction :size="13" :stroke-width="1.75" :class="maintenance.enabled ? 'text-amber-400' : 'text-zinc-600'" />
                        <span class="text-sm font-semibold text-zinc-100">Maintenance</span>
                        <span
                            class="ml-auto text-[10px] font-bold px-2 py-0.5 rounded border"
                            :class="maintenance.enabled
                                ? 'bg-amber-500/10 text-amber-400 border-amber-500/20'
                                : 'bg-zinc-800 text-zinc-500 border-zinc-700'"
                        >{{ maintenance.enabled ? 'ON' : 'OFF' }}</span>
                    </div>

                    <div class="p-4">
                        <template v-if="!maintenance.enabled">
                            <p class="text-zinc-500 text-xs mb-3">Visitors will see a 503 page. Admins can still browse normally.</p>
                            <button
                                type="button"
                                class="w-full px-3 py-2 rounded-lg text-sm font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/25 hover:bg-amber-500/20 transition-colors"
                                @click="openEnableModal"
                            >
                                Enable Maintenance Mode
                            </button>
                        </template>
                        <template v-else>
                            <p v-if="maintenance.message" class="text-zinc-400 text-xs mb-3 italic">"{{ maintenance.message }}"</p>
                            <p class="text-zinc-500 text-xs mb-3">Public site shows 503. Admins can still browse.</p>
                            <button
                                type="button"
                                class="w-full px-3 py-2 rounded-lg text-sm font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/25 hover:bg-emerald-500/20 transition-colors"
                                @click="disableMaintenance"
                            >
                                Disable Maintenance Mode
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Cache -->
                <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden" style="animation-delay: 240ms">
                    <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <HardDrive :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Cache</span>
                    </div>
                    <div class="p-3 flex flex-col gap-1.5">
                        <button
                            v-for="action in cacheActions"
                            :key="action.route"
                            type="button"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-zinc-400 hover:text-zinc-100 bg-zinc-900/60 border border-zinc-800/70 hover:border-zinc-700 transition-colors text-left"
                            @click="runAction(action.route, action.label)"
                        >
                            <Eraser :size="12" :stroke-width="1.75" class="text-zinc-600 shrink-0" />
                            {{ action.label }}
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </AdminLayout>

    <!-- Maintenance enable modal -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background:rgba(0,0,0,0.7);backdrop-filter:blur(4px)"
                @click.self="showModal = false"
            >
                <div class="bg-[#111113] border border-zinc-800 rounded-2xl w-full max-w-md shadow-2xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800">
                        <div class="flex items-center gap-2.5">
                            <Construction :size="16" :stroke-width="1.75" class="text-amber-400" />
                            <span class="font-semibold text-zinc-100">Enable Maintenance Mode</span>
                        </div>
                        <button type="button" class="text-zinc-600 hover:text-zinc-300 transition-colors" @click="showModal = false">
                            <X :size="16" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-5 flex flex-col gap-4">
                        <p class="text-sm text-zinc-400">
                            The public site will show a maintenance page to all visitors.
                            Admins can still browse normally.
                        </p>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-zinc-400">
                                Message shown to visitors
                                <span class="text-zinc-600">(leave empty for default)</span>
                            </label>
                            <textarea
                                v-model="maintenanceForm.message"
                                rows="3"
                                :class="inputClass"
                                :placeholder="DEFAULT_MAINTENANCE_MSG"
                                autofocus
                            />
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-zinc-800">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 border border-zinc-800 transition-colors"
                            @click="showModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg text-sm font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30 hover:bg-amber-500/25 transition-colors"
                            :disabled="maintenanceForm.processing"
                            @click="confirmEnable"
                        >
                            Enable
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
