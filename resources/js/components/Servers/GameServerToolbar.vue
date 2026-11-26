<script setup lang="ts">
import { LayoutGrid, List, RefreshCw, Search, X } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import type { ServerFilter } from './types';

defineProps<{ counts: Record<ServerFilter, number>; resultCount: number; refreshing: boolean }>();
defineEmits<{ refresh: [] }>();

const search = defineModel<string>('search', { required: true });
const filter = defineModel<ServerFilter>('filter', { required: true });
const view = defineModel<'table' | 'grid'>('view', { required: true });

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const filters = computed<{ key: ServerFilter; label: string }[]>(() => [
    { key: 'all', label: t('servers.filter_all') },
    { key: 'online', label: t('servers.online') },
    { key: 'offline', label: t('servers.offline') },
    { key: 'empty', label: t('servers.filter_empty') },
    { key: 'hasplayers', label: t('servers.filter_has_players') },
    { key: 'full', label: t('servers.filter_full') },
]);

const views = computed(() => [
    { key: 'table' as const, icon: List, label: t('servers.view_table') },
    { key: 'grid' as const, icon: LayoutGrid, label: t('servers.view_grid') },
]);
</script>

<template>
    <div>
        <p class="text-[12.5px] mb-2.5 text-zinc-500">{{ t('servers.filter_hint') }}</p>
        <div class="flex items-center gap-1.5 mb-4 flex-wrap" role="group" :aria-label="t('servers.filter_group')">
            <button
                v-for="item in filters"
                :key="item.key"
                type="button"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                :class="filter === item.key
                    ? 'bg-blue-600 border-blue-600 text-white'
                    : dark
                        ? 'border-zinc-800/80 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 hover:bg-white/[0.04]'
                        : 'border-zinc-300 bg-white text-zinc-500 hover:text-zinc-900 hover:border-zinc-400'"
                :aria-pressed="filter === item.key"
                @click="filter = item.key"
            >
                {{ item.label }}
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold tabular-nums"
                    :class="filter === item.key
                        ? 'bg-black/25 text-white'
                        : dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-500'">{{ counts[item.key] }}</span>
            </button>
        </div>

        <div class="flex items-center gap-3 mb-4 flex-wrap">
            <div class="flex-1 min-w-[200px] max-w-sm">
                <label for="server-search" class="sr-only">{{ t('servers.search') }}</label>
                <div class="relative">
                    <Search :size="14" :stroke-width="1.8" aria-hidden="true"
                        class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-zinc-500" />
                    <input
                        id="server-search"
                        v-model="search"
                        type="search"
                        autocomplete="off"
                        maxlength="100"
                        :placeholder="t('servers.search')"
                        class="w-full pl-9 pr-9 py-2.5 rounded-lg border text-[13px] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                        :class="dark
                            ? 'border-zinc-800/80 bg-[#111113] text-zinc-100 placeholder:text-zinc-500 focus:border-zinc-700'
                            : 'border-zinc-300 bg-white text-zinc-900 placeholder:text-zinc-500 focus:border-blue-500/60'"
                    />
                    <button v-if="search" type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center rounded transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-500 hover:text-zinc-900'"
                        :aria-label="t('servers.clear_search')" :title="t('servers.clear_search')"
                        @click="search = ''">
                        <X :size="14" :stroke-width="2" aria-hidden="true" />
                    </button>
                </div>
                <p class="sr-only" role="status" aria-live="polite">
                    {{ t('servers.results_servers', { count: resultCount }) }}
                </p>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <div class="flex items-center rounded-lg border overflow-hidden" role="group"
                    :aria-label="t('servers.view_group')"
                    :class="dark ? 'border-zinc-800/80' : 'border-zinc-300'">
                    <button
                        v-for="item in views"
                        :key="item.key"
                        type="button"
                        class="px-3 py-2.5 flex items-center gap-1.5 text-[12px] font-semibold transition border-r last:border-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                        :class="[
                            view === item.key
                                ? dark ? 'bg-zinc-800 text-zinc-100' : 'bg-zinc-200 text-zinc-900'
                                : dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-500 hover:text-zinc-900',
                            dark ? 'border-zinc-800/80' : 'border-zinc-300',
                        ]"
                        :aria-pressed="view === item.key"
                        @click="view = item.key"
                    >
                        <component :is="item.icon" :size="13" :stroke-width="1.8" aria-hidden="true" />
                        {{ item.label }}
                    </button>
                </div>

                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-2.5 rounded-lg border text-[12px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 disabled:opacity-60"
                    :class="dark
                        ? 'border-zinc-800/80 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700'
                        : 'border-zinc-300 text-zinc-500 hover:text-zinc-900 hover:border-zinc-400'"
                    :disabled="refreshing"
                    @click="$emit('refresh')"
                >
                    <RefreshCw :size="13" :stroke-width="1.8" aria-hidden="true" :class="refreshing ? 'animate-spin' : ''" />
                    {{ t('servers.refresh') }}
                </button>
            </div>
        </div>
    </div>
</template>
