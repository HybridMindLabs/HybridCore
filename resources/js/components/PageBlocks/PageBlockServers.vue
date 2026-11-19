<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Play } from '@lucide/vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

interface BlockServer {
    id: number; name: string; address: string; game: string | null; game_slug: string | null; map: string | null;
    players: number; max_players: number; online: boolean; url: string; connect_url: string;
}

defineProps<{ data: Record<string, any>; props: { servers: BlockServer[] } }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <div>
        <h2 v-if="data.title" class="mb-4 text-[22px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ data.title }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="s in props.servers" :key="s.id" class="flex items-center gap-3 rounded-xl px-4 py-3 ring-1"
                :class="dark ? 'bg-[#111113] ring-white/[0.06]' : 'bg-white ring-zinc-200'">
                <span class="relative shrink-0 w-10 h-10 rounded-lg overflow-hidden">
                    <GameIcon v-if="s.game_slug" :slug="s.game_slug" :alt="s.game ?? ''" />
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2"
                        :class="[s.online ? 'bg-emerald-500' : 'bg-zinc-500', dark ? 'border-[#111113]' : 'border-white']"
                        role="img" :aria-label="s.online ? t('home.status_online') : t('home.status_offline')" />
                </span>
                <div class="min-w-0 flex-1">
                    <Link :href="s.url" class="block truncate text-[14px] font-bold hover:text-hc-accent transition-colors"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ s.name }}</Link>
                    <p class="truncate text-[12px] font-mono" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ s.address }}<template v-if="s.map"> · {{ s.map }}</template>
                    </p>
                </div>
                <span class="shrink-0 rounded-md px-2.5 py-1 text-[12px] font-bold tabular-nums"
                    :class="s.online ? 'bg-emerald-500/15 text-emerald-500' : (dark ? 'bg-zinc-800 text-zinc-500' : 'bg-zinc-100 text-zinc-500')">
                    {{ s.online ? `${s.players}/${s.max_players}` : t('servers.offline') }}
                </span>
                <a :href="s.connect_url" class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                    :class="dark ? 'text-zinc-500 hover:text-emerald-400 hover:bg-emerald-500/10' : 'text-zinc-500 hover:text-emerald-700 hover:bg-emerald-500/10'"
                    :aria-label="t('home.connect_to', { name: s.name })" :title="t('home.connect_to', { name: s.name })">
                    <Play :size="15" fill="currentColor" aria-hidden="true" />
                </a>
            </div>
        </div>
        <p v-if="!props.servers.length" class="text-[14px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('servers.no_servers') }}</p>
    </div>
</template>
