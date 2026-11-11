<script setup lang="ts">
import { X } from '@lucide/vue';

defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();

const groups = [
    {
        title: 'General',
        items: [
            { keys: ['Ctrl', 'K'], label: 'Search the admin panel' },
            { keys: ['?'], label: 'Show this list' },
            { keys: ['Esc'], label: 'Close a dialog or search' },
        ],
    },
    {
        title: 'Go to',
        items: [
            { keys: ['G', 'D'], label: 'Dashboard' },
            { keys: ['G', 'U'], label: 'Users' },
            { keys: ['G', 'V'], label: 'Servers' },
            { keys: ['G', 'P'], label: 'Payments' },
            { keys: ['G', 'S'], label: 'Settings' },
        ],
    },
];
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[105] flex items-center justify-center px-4" @keydown.esc="emit('close')">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @mousedown="emit('close')" />
            <div role="dialog" aria-modal="true" aria-labelledby="shortcuts-title" class="relative w-full max-w-md rounded-2xl border border-zinc-800 bg-admin-surface shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800/70">
                    <h2 id="shortcuts-title" class="text-sm font-semibold text-zinc-100">Keyboard shortcuts</h2>
                    <button type="button" class="w-8 h-8 rounded-lg flex items-center justify-center text-zinc-500 hover:text-zinc-100 hover:bg-admin-elevated" aria-label="Close" @click="emit('close')">
                        <X :size="16" />
                    </button>
                </div>
                <div class="px-5 py-4 space-y-5">
                    <section v-for="g in groups" :key="g.title">
                        <h3 class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500 mb-2">{{ g.title }}</h3>
                        <ul class="space-y-2">
                            <li v-for="item in g.items" :key="item.label" class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-zinc-300">{{ item.label }}</span>
                                <span class="flex items-center gap-1">
                                    <template v-for="(k, i) in item.keys" :key="k">
                                        <span v-if="i > 0 && g.title === 'Go to'" class="text-zinc-600 text-xs">then</span>
                                        <kbd class="min-w-[24px] text-center text-[11px] font-mono px-1.5 py-0.5 rounded bg-zinc-900 border border-zinc-800 text-zinc-300">{{ k }}</kbd>
                                    </template>
                                </span>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </Teleport>
</template>
