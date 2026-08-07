<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Download, Trash2, Terminal, HardDrive, Hash, AlertTriangle, Info, RefreshCw, Search } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import Tooltip from '@/components/UI/Tooltip.vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    logSizeKb: number;
    logPath: string;
    lineCount: number;
    tail: string[];
}>();

const sizeLabel = computed(() => {
    if (props.logSizeKb >= 1024) return (props.logSizeKb / 1024).toFixed(1) + ' MB';
    return props.logSizeKb + ' KB';
});

const sizeWarn = computed(() => props.logSizeKb > 5120);

function levelOf(line: string): 'error' | 'warning' | 'info' | 'debug' | 'other' {
    if (line.includes('.ERROR') || line.includes('ERROR:')) return 'error';
    if (line.includes('.WARNING') || line.includes('WARNING:')) return 'warning';
    if (line.includes('.INFO') || line.includes('INFO:')) return 'info';
    if (line.includes('.DEBUG') || line.includes('DEBUG:')) return 'debug';
    return 'other';
}

const LEVEL_CLASS: Record<string, string> = {
    error: 'text-red-400',
    warning: 'text-amber-400',
    info: 'text-blue-400',
    debug: 'text-zinc-600',
    other: 'text-zinc-500',
};

function lineClass(line: string): string {
    return LEVEL_CLASS[levelOf(line)];
}

const levelFilter = ref<'all' | 'error' | 'warning'>('all');
const search = ref('');

const errorCount = computed(() => props.tail.filter((l) => levelOf(l) === 'error').length);
const warningCount = computed(() => props.tail.filter((l) => levelOf(l) === 'warning').length);

const filteredTail = computed(() => {
    return props.tail.filter((line) => {
        if (levelFilter.value !== 'all' && levelOf(line) !== levelFilter.value) return false;
        if (search.value && !line.toLowerCase().includes(search.value.toLowerCase())) return false;
        return true;
    });
});

const refreshing = ref(false);
function refresh() {
    refreshing.value = true;
    router.reload({ only: ['logSizeKb', 'logPath', 'lineCount', 'tail'], onFinish: () => { refreshing.value = false; } });
}

function clearLog() {
    if (confirm('Clear the log file? This cannot be undone.')) {
        router.post(route('admin.system-logs.clear'));
    }
}
</script>

<template>
    <Head title="System Logs" />
    <AdminLayout title="System Logs">

        <PageHeader title="System Logs" description="Laravel application log viewer." :icon="FileText">
            <template #actions>
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800/70 bg-zinc-900/60 text-zinc-400 text-xs font-semibold hover:text-zinc-100 hover:border-zinc-700 transition-colors disabled:opacity-50"
                    :disabled="refreshing"
                    @click="refresh"
                >
                    <RefreshCw :size="12" :stroke-width="2" :class="refreshing ? 'animate-spin' : ''" /> {{ refreshing ? 'Refreshing…' : 'Refresh' }}
                </button>
                <a
                    :href="route('admin.system-logs.download')"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                >
                    <Download :size="12" :stroke-width="2" />
                    Download
                </a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_260px] gap-5 items-start">

            <!-- Left: log tail -->
            <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-zinc-800/70">
                    <Terminal :size="13" :stroke-width="1.75" class="text-zinc-600" />
                    <span class="text-sm font-semibold text-zinc-100">Last 50 lines</span>
                    <span v-if="filteredTail.length !== tail.length" class="text-xs text-zinc-600">({{ filteredTail.length }} shown)</span>
                    <span class="ml-auto text-xs text-zinc-600 font-mono hidden sm:inline">{{ logPath }}</span>
                </div>

                <div v-if="tail.length > 0" class="flex flex-wrap items-center gap-2 px-4 py-2.5 border-b border-zinc-800/70 bg-black/20">
                    <button
                        v-for="lvl in (['all', 'error', 'warning'] as const)"
                        :key="lvl"
                        type="button"
                        class="px-2.5 py-1 rounded-md text-[11px] font-semibold capitalize transition-colors"
                        :class="levelFilter === lvl ? 'bg-zinc-700 text-zinc-100' : 'text-zinc-500 hover:text-zinc-300'"
                        @click="levelFilter = lvl"
                    >
                        {{ lvl }}
                        <span v-if="lvl === 'error' && errorCount" class="text-red-400">({{ errorCount }})</span>
                        <span v-else-if="lvl === 'warning' && warningCount" class="text-amber-400">({{ warningCount }})</span>
                    </button>
                    <div class="relative ml-auto">
                        <Search :size="12" :stroke-width="2" class="absolute left-2 top-1/2 -translate-y-1/2 text-zinc-600" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Filter lines…"
                            class="pl-7 pr-2 py-1 w-40 rounded-md bg-zinc-900 border border-zinc-800 text-xs text-zinc-200 placeholder:text-zinc-600 focus:outline-none focus:border-zinc-600"
                        />
                    </div>
                </div>

                <div v-if="tail.length > 0 && filteredTail.length > 0" class="overflow-x-auto">
                    <pre class="p-4 text-[11px] font-mono leading-relaxed whitespace-pre-wrap break-all"><template v-for="(line, i) in filteredTail" :key="i"><span :class="lineClass(line)">{{ line }}</span>{{ '\n' }}</template></pre>
                </div>

                <div v-else-if="tail.length > 0" class="py-16 text-center">
                    <Search :size="28" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                    <p class="text-zinc-500 text-sm">No lines match this filter.</p>
                </div>

                <div v-else class="py-16 text-center">
                    <FileText :size="28" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                    <p class="text-zinc-500 text-sm">Log file is empty.</p>
                </div>
            </div>

            <!-- Right sidebar -->
            <div class="flex flex-col gap-4">

                <!-- File info -->
                <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden" style="animation-delay: 40ms">
                    <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <HardDrive :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">File Info</span>
                    </div>
                    <div class="divide-y divide-zinc-800/40">
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <Tooltip text="Current size of storage/logs/laravel.log">
                                <span class="text-xs text-zinc-500 flex items-center gap-1.5 cursor-default">
                                    <HardDrive :size="11" :stroke-width="1.75" />
                                    Size
                                    <Info :size="10" :stroke-width="2" class="text-zinc-700" />
                                </span>
                            </Tooltip>
                            <span
                                class="text-xs font-mono font-semibold"
                                :class="sizeWarn ? 'text-amber-400' : 'text-zinc-300'"
                            >{{ sizeLabel }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <Tooltip text="Total lines in the full log file, not just the tail shown here">
                                <span class="text-xs text-zinc-500 flex items-center gap-1.5 cursor-default">
                                    <Hash :size="11" :stroke-width="1.75" />
                                    Lines
                                    <Info :size="10" :stroke-width="2" class="text-zinc-700" />
                                </span>
                            </Tooltip>
                            <span class="text-xs font-mono text-zinc-300">{{ lineCount.toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Size warning -->
                <div
                    v-if="sizeWarn"
                    class="hc-hero-in flex items-start gap-3 bg-amber-500/5 border border-amber-500/20 rounded-xl px-4 py-3"
                    style="animation-delay: 80ms"
                >
                    <AlertTriangle :size="14" :stroke-width="1.75" class="text-amber-400 mt-0.5 shrink-0" />
                    <p class="text-xs text-zinc-400 leading-relaxed">Log file is large. Consider clearing it to free disk space.</p>
                </div>

                <!-- Actions -->
                <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden" style="animation-delay: 120ms">
                    <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <FileText :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Actions</span>
                    </div>
                    <div class="p-3 flex flex-col gap-2">
                        <a
                            :href="route('admin.system-logs.download')"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                        >
                            <Download :size="13" :stroke-width="1.75" />
                            Download Log File
                        </a>
                        <button
                            type="button"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-red-400 bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 transition-colors"
                            @click="clearLog"
                        >
                            <Trash2 :size="13" :stroke-width="1.75" />
                            Clear Log File
                        </button>
                    </div>
                </div>

                <!-- Info notice -->
                <div class="hc-hero-in flex items-start gap-3 bg-blue-500/5 border border-blue-500/20 rounded-xl px-4 py-3" style="animation-delay: 160ms">
                    <Info :size="14" :stroke-width="1.75" class="text-blue-400 mt-0.5 shrink-0" />
                    <p class="text-xs text-zinc-400 leading-relaxed">
                        Showing last 50 lines. Full log available via Download or at
                        <code class="text-blue-400 font-mono">{{ logPath }}</code> on the server.
                    </p>
                </div>

            </div>
        </div>

    </AdminLayout>
</template>
