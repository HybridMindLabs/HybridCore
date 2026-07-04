<script setup lang="ts">
import { Monitor, MapPin, Clock } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { computed } from 'vue';

interface LoginEntry {
    id: number; ip: string | null; user_agent: string | null;
    country: string | null; city: string | null;
    at: string; at_full: string;
}
interface Paginator { data: LoginEntry[]; links: any; meta: any }

defineProps<{ history: Paginator }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

function parseBrowser(ua: string | null): string {
    if (!ua) return 'Unknown';
    if (ua.includes('Edg/')) return 'Edge';
    if (ua.includes('Chrome/')) return 'Chrome';
    if (ua.includes('Firefox/')) return 'Firefox';
    if (ua.includes('Safari/') && !ua.includes('Chrome')) return 'Safari';
    if (ua.includes('OPR/') || ua.includes('Opera')) return 'Opera';
    return 'Unknown';
}
function parseOS(ua: string | null): string {
    if (!ua) return '';
    if (ua.includes('Windows NT')) return 'Windows';
    if (ua.includes('Mac OS X')) return 'macOS';
    if (ua.includes('Android')) return 'Android';
    if (ua.includes('iPhone') || ua.includes('iPad')) return 'iOS';
    if (ua.includes('Linux')) return 'Linux';
    return '';
}
</script>

<template>
    <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
        <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Login History</p>
            <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Recent sign-ins to your account.</p>
        </div>

        <div v-if="history.data.length === 0" class="p-12 text-center">
            <Monitor :size="28" :stroke-width="1.5" class="mx-auto mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
            <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No login history yet</p>
        </div>

        <div v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-100'">
            <div v-for="entry in history.data" :key="entry.id" class="flex items-start gap-4 px-5 py-4">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 border mt-0.5"
                    :class="dark ? 'border-zinc-800 bg-zinc-900' : 'border-zinc-200 bg-zinc-50'">
                    <Monitor :size="13" :stroke-width="1.8" :class="dark ? 'text-zinc-500' : 'text-zinc-400'" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                            {{ parseBrowser(entry.user_agent) }}
                            <span class="font-normal" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">on {{ parseOS(entry.user_agent) }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-4 mt-1 flex-wrap">
                        <span v-if="entry.ip" class="flex items-center gap-1 text-[11px] font-mono" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                            {{ entry.ip }}
                        </span>
                        <span v-if="entry.city || entry.country" class="flex items-center gap-1 text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                            <MapPin :size="10" :stroke-width="2" />
                            {{ [entry.city, entry.country].filter(Boolean).join(', ') }}
                        </span>
                        <span class="flex items-center gap-1 text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'" :title="entry.at_full">
                            <Clock :size="10" :stroke-width="2" /> {{ entry.at }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
