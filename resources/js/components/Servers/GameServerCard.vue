<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Play } from '@lucide/vue';
import { computed, ref } from 'vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import ServerCopyButton from './ServerCopyButton.vue';
import ServerFavouriteButton from './ServerFavouriteButton.vue';
import { pingClass, playerBarColor, type GameInfo, type GameServer } from './types';

const props = defineProps<{ game: GameInfo; server: GameServer }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const imageFailed = ref(false);
const online = computed(() => !!props.server.status?.is_online);
const target = computed(() => ({ game: props.game.slug, ip: props.server.ip, port: props.server.port }));
</script>

<template>
    <div
        class="hc-reveal group relative rounded-2xl border overflow-hidden transition-all duration-200 hover:-translate-y-1"
        :class="dark
            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700 hover:shadow-xl hover:shadow-black/40'
            : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-[0_10px_28px_rgba(0,0,0,0.10)] hover:border-zinc-300'"
    >
        <!-- Map artwork as the card header -->
        <div class="relative h-[104px] overflow-hidden">
            <img v-if="server.row_image && !imageFailed"
                :src="server.row_image" alt="" loading="lazy" decoding="async"
                class="absolute inset-0 w-full h-full object-cover transform-gpu
                       transition-transform duration-[900ms] ease-out group-hover:scale-[1.06]
                       motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                :class="dark ? 'opacity-70' : 'opacity-85'"
                @error="imageFailed = true" />
            <div v-else class="absolute inset-0"
                :style="{ background: `linear-gradient(135deg, ${game.color}45, ${game.color}12)` }" />

            <div class="absolute inset-0" aria-hidden="true"
                :style="dark
                    ? 'background: linear-gradient(to bottom, rgba(17,17,19,0.10) 0%, rgba(17,17,19,0.62) 55%, rgba(17,17,19,1) 100%)'
                    : 'background: linear-gradient(to bottom, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0.68) 55%, rgba(255,255,255,1) 100%)'" />

            <!-- Players pill -->
            <span v-if="server.status?.is_online"
                class="absolute top-3 right-3 px-2.5 py-1 rounded-md text-[11.5px] font-bold tabular-nums text-white"
                :style="{ backgroundColor: playerBarColor(server.status) }">
                {{ server.status.players_online }}/{{ server.status.players_max }}
            </span>
            <span v-else
                class="absolute top-3 right-3 px-2.5 py-1 rounded-md text-[10.5px] font-bold backdrop-blur-md"
                :class="dark ? 'bg-zinc-900/80 text-zinc-400' : 'bg-white/85 text-zinc-500'">
                {{ t('servers.offline') }}
            </span>

            <div class="absolute bottom-3 left-4 flex items-center gap-2">
                <span class="w-9 h-9 rounded-lg overflow-hidden ring-2 shadow-lg" :class="dark ? 'ring-zinc-800' : 'ring-white'">
                    <GameIcon :slug="game.slug" :alt="game.name" />
                </span>
                <span class="w-3 h-3 rounded-full border-2"
                    :class="[
                        online ? 'bg-emerald-500' : dark ? 'bg-zinc-600' : 'bg-zinc-400',
                        dark ? 'border-[#111113]' : 'border-white',
                    ]"
                    role="img"
                    :aria-label="online ? t('home.status_online') : t('home.status_offline')" />
            </div>
        </div>

        <div class="px-4 pb-4 pt-2.5">
            <Link :href="route('servers.show', target)"
                class="block text-[14px] font-black truncate transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-600'">
                {{ server.name }}
            </Link>

            <div class="flex items-center gap-2 mt-1 text-[11.5px] font-mono min-w-0 text-zinc-500">
                <span class="truncate">{{ server.status?.map ?? '—' }}</span>
                <span v-if="server.status?.ping != null" class="shrink-0" :class="pingClass(server.status, dark)">
                    · {{ server.status.ping }}ms
                </span>
            </div>

            <div class="flex items-center gap-1.5 mt-3">
                <span class="flex-1 min-w-0 text-[11.5px] font-mono truncate" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ server.address }}</span>
                <ServerCopyButton :address="server.address" />
                <ServerFavouriteButton :server="server" size="sm" />
                <a :href="route('servers.connect', target)"
                    class="w-7 h-7 flex items-center justify-center rounded-md transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="dark ? 'text-zinc-500 hover:text-emerald-400 hover:bg-emerald-500/10' : 'text-zinc-500 hover:text-emerald-800 hover:bg-emerald-500/10'"
                    :aria-label="t('home.connect_to', { name: server.name })"
                    :title="t('home.connect_to', { name: server.name })">
                    <Play :size="14" :stroke-width="2" fill="currentColor" aria-hidden="true" />
                </a>
            </div>
        </div>
    </div>
</template>
