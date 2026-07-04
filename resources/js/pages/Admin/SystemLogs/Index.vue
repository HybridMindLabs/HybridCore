<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Download, Trash2, Terminal, HardDrive, Hash, AlertTriangle, Info } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { computed } from 'vue';

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

function lineClass(line: string): string {
    if (line.includes('.ERROR') || line.includes('ERROR:')) return 'text-red-400';
    if (line.includes('.WARNING') || line.includes('WARNING:')) return 'text-amber-400';
    if (line.includes('.INFO') || line.includes('INFO:')) return 'text-blue-400';
    if (line.includes('.DEBUG') || line.includes('DEBUG:')) return 'text-zinc-600';
    return 'text-zinc-500';
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
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-zinc-800/70">
                    <Terminal :size="13" :stroke-width="1.75" class="text-zinc-600" />
                    <span class="text-sm font-semibold text-zinc-100">Last 50 lines</span>
                    <span class="ml-auto text-xs text-zinc-600 font-mono">{{ logPath }}</span>
                </div>

                <div v-if="tail.length > 0" class="overflow-x-auto">
                    <pre class="p-4 text-[11px] font-mono leading-relaxed whitespace-pre-wrap break-all"><template v-for="(line, i) in tail" :key="i"><span :class="lineClass(line)">{{ line }}</span>{{ '\n' }}</template></pre>
                </div>

                <div v-else class="py-16 text-center">
                    <FileText :size="28" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                    <p class="text-zinc-500 text-sm">Log file is empty.</p>
                </div>
            </div>

            <!-- Right sidebar -->
            <div class="flex flex-col gap-4">

                <!-- File info -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <HardDrive :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">File Info</span>
                    </div>
                    <div class="divide-y divide-zinc-800/40">
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <HardDrive :size="11" :stroke-width="1.75" />
                                Size
                            </span>
                            <span
                                class="text-xs font-mono font-semibold"
                                :class="sizeWarn ? 'text-amber-400' : 'text-zinc-300'"
                            >{{ sizeLabel }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <Hash :size="11" :stroke-width="1.75" />
                                Lines
                            </span>
                            <span class="text-xs font-mono text-zinc-300">{{ lineCount.toLocaleString() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Size warning -->
                <div
                    v-if="sizeWarn"
                    class="flex items-start gap-3 bg-amber-500/5 border border-amber-500/20 rounded-xl px-4 py-3"
                >
                    <AlertTriangle :size="14" :stroke-width="1.75" class="text-amber-400 mt-0.5 shrink-0" />
                    <p class="text-xs text-zinc-400 leading-relaxed">Log file is large. Consider clearing it to free disk space.</p>
                </div>

                <!-- Actions -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
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
                <div class="flex items-start gap-3 bg-blue-500/5 border border-blue-500/20 rounded-xl px-4 py-3">
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
