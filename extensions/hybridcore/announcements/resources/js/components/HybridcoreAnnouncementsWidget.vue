<script setup lang="ts">
import { computed } from 'vue';
import { Info, CheckCircle, AlertTriangle, XCircle } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';

interface Announcement {
    id: number;
    title: string;
    body: string;
    type: 'info' | 'success' | 'warning' | 'danger';
}

const props = defineProps<{ announcements: Announcement[] }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const config = {
    info:    { icon: Info,          color: 'text-blue-400',   bg: 'bg-blue-500/10',   border: 'border-blue-500/20'   },
    success: { icon: CheckCircle,   color: 'text-emerald-400', bg: 'bg-emerald-500/10', border: 'border-emerald-500/20' },
    warning: { icon: AlertTriangle, color: 'text-amber-400',  bg: 'bg-amber-500/10',  border: 'border-amber-500/20'  },
    danger:  { icon: XCircle,       color: 'text-red-400',    bg: 'bg-red-500/10',    border: 'border-red-500/20'    },
} as const;
</script>

<template>
    <div v-if="announcements.length" class="flex flex-col gap-2">
        <div
            v-for="item in announcements"
            :key="item.id"
            class="flex gap-3 p-3 rounded-xl border text-[12px]"
            :class="[
                config[item.type].bg,
                config[item.type].border,
                dark ? 'bg-opacity-100' : 'bg-opacity-80',
            ]"
        >
            <component
                :is="config[item.type].icon"
                :size="14"
                :stroke-width="2"
                class="shrink-0 mt-[1px]"
                :class="config[item.type].color"
            />
            <div>
                <p class="font-bold leading-tight mb-0.5" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                    {{ item.title }}
                </p>
                <p class="leading-relaxed" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                    {{ item.body }}
                </p>
            </div>
        </div>
    </div>
</template>
