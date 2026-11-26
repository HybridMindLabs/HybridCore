<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock, Play, Shield } from '@lucide/vue';
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
        class="hc-server-row group relative grid grid-cols-[minmax(0,1fr)_auto_auto] lg:grid-cols-[minmax(0,1fr)_240px_170px_104px_72px_84px] items-center border-b last:border-0 overflow-hidden transition-colors"
        :class="dark ? 'border-zinc-800/50 hover:bg-white/[0.03]' : 'border-zinc-100 hover:bg-zinc-100/70'"
    >
        <!-- Current map bleeding in from the right, masked away before it reaches the text. -->
        <div v-if="server.row_image && !imageFailed"
            class="absolute inset-y-0 right-0 w-[45%] pointer-events-none hidden sm:block"
            aria-hidden="true">
            <!-- Timing lives with .hc-server-map in app.css — see the note there. -->
            <img :src="server.row_image" alt="" loading="lazy" decoding="async"
                class="hc-server-map w-full h-full object-cover transform-gpu
                       group-hover:scale-[1.06] motion-reduce:group-hover:scale-100"
                :class="dark ? 'opacity-[0.4] group-hover:opacity-[0.55]' : 'opacity-[0.2] group-hover:opacity-[0.3]'"
                @error="imageFailed = true" />
        </div>

        <span class="absolute left-0 inset-y-0 w-[3px]" aria-hidden="true"
            :style="{ background: game.color }"
            :class="online ? 'opacity-100' : 'opacity-30'" />

        <!-- Name -->
        <div class="relative px-5 py-3.5 flex items-center gap-3 min-w-0">
            <div class="relative shrink-0">
                <span class="block w-9 h-9 rounded-lg overflow-hidden">
                    <GameIcon :slug="game.slug" :alt="game.name" />
                </span>
                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2"
                    :class="[
                        online ? 'bg-emerald-500' : dark ? 'bg-zinc-600' : 'bg-zinc-400',
                        dark ? 'border-[#111113]' : 'border-white',
                    ]"
                    role="img"
                    :aria-label="online ? t('home.status_online') : t('home.status_offline')"
                    :title="online ? t('home.status_online') : t('home.status_offline')" />
            </div>

            <div class="min-w-0 flex-1">
                <Link :href="route('servers.show', target)"
                    class="block text-[13.5px] font-bold truncate transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                    :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-600'">
                    {{ server.name }}
                </Link>
                <div class="flex items-center gap-1.5 mt-1 min-w-0 text-[11.5px]">
                    <Lock v-if="server.status?.is_password_protected" :size="11" :stroke-width="2"
                        class="text-amber-500 shrink-0" :aria-label="t('servers.password_protected')" />
                    <Shield v-if="server.status?.vac_secured" :size="11" :stroke-width="2"
                        class="text-emerald-500 shrink-0" :aria-label="t('servers.vac_secured')" />
                    <span v-if="server.country_code"
                        class="shrink-0 px-1.5 py-0.5 rounded text-[9px] font-bold tracking-wide"
                        :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-500'">{{ server.country_code.toUpperCase() }}</span>
                    <span v-for="tag in server.tags.slice(0, 3)" :key="tag"
                        class="hidden lg:inline shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded"
                        :class="dark ? 'bg-zinc-800/80 text-zinc-400' : 'bg-zinc-200 text-zinc-500'">{{ tag }}</span>
                    <!-- Below lg the map, address and ping columns are gone, so map and ping move under the name. -->
                    <span class="lg:hidden font-mono truncate" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                        {{ server.status?.map || '—' }}
                    </span>
                    <span v-if="server.status?.ping != null" class="lg:hidden font-mono shrink-0" :class="pingClass(server.status, dark)">
                        · {{ server.status.ping }}ms
                    </span>
                </div>
            </div>
        </div>

        <!-- Address + copy -->
        <div class="relative hidden lg:flex px-4 items-center gap-1 min-w-0">
            <span class="text-[12px] font-mono truncate" :class="dark ? 'text-zinc-300' : 'text-zinc-600'"
                :title="server.address">{{ server.address }}</span>
            <ServerCopyButton :address="server.address" />
        </div>

        <!-- Map -->
        <div class="relative hidden lg:block px-4 min-w-0">
            <span class="block text-[12.5px] font-mono truncate"
                :class="server.status?.map ? (dark ? 'text-zinc-200' : 'text-zinc-700') : (dark ? 'text-zinc-600' : 'text-zinc-400')"
                :title="server.status?.map || undefined">{{ server.status?.map || '—' }}</span>
        </div>

        <!-- Players -->
        <div class="relative flex items-center justify-center px-2 lg:px-4 py-3">
            <span v-if="server.status?.is_online"
                class="px-2.5 py-1 rounded-md text-[12px] font-bold tabular-nums text-white min-w-[62px] text-center"
                :style="{ backgroundColor: playerBarColor(server.status) }"
                :title="t('servers.players_of_max', { players: server.status.players_online, max: server.status.players_max })">
                {{ server.status.players_online }}/{{ server.status.players_max }}
            </span>
            <span v-else class="px-2.5 py-1 rounded-md text-[11px] font-bold min-w-[62px] text-center"
                :class="dark ? 'bg-zinc-800 text-zinc-500' : 'bg-zinc-200 text-zinc-500'">
                {{ t('servers.offline') }}
            </span>
        </div>

        <!-- Ping -->
        <div class="relative hidden lg:flex px-4 items-center justify-end">
            <span v-if="server.status?.ping != null" class="text-[12.5px] font-mono tabular-nums" :class="pingClass(server.status, dark)">
                {{ server.status.ping }}ms
            </span>
            <span v-else class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">—</span>
        </div>

        <!-- Connect + favourite -->
        <div class="relative flex items-center justify-end gap-0.5 pr-3 lg:pr-4">
            <a :href="route('servers.connect', target)"
                class="hidden lg:flex w-8 h-8 items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                :class="dark ? 'text-zinc-500 hover:text-emerald-400 hover:bg-emerald-500/10' : 'text-zinc-500 hover:text-emerald-800 hover:bg-emerald-500/10'"
                :aria-label="t('home.connect_to', { name: server.name })"
                :title="t('home.connect_to', { name: server.name })">
                <Play :size="15" :stroke-width="2" fill="currentColor" aria-hidden="true" />
            </a>
            <ServerFavouriteButton :server="server" />
        </div>
    </div>
</template>
