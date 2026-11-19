<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { Star, Copy, Check, Play } from '@lucide/vue';
import { ref, computed, watch, onUnmounted } from 'vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { gameColor, pingColor, type HomeServer } from './types';

const props = defineProps<{ server: HomeServer }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ auth: { user: unknown } | null }>();

const mapFailed = ref(false);
const favourited = ref(props.server.is_favourited);
watch(() => props.server.is_favourited, (value) => { favourited.value = value; });

const fillPercent = computed(() => props.server.max_players
    ? Math.round(props.server.players / props.server.max_players * 100) : 0);

/** Blue when quiet, green once it has a crowd, amber filling up, red when full. */
const fillColor = computed(() => {
    if (!props.server.is_online || !props.server.max_players) return '#71717a';
    if (fillPercent.value >= 95) return '#ef4444';
    if (fillPercent.value >= 75) return '#f59e0b';
    if (fillPercent.value >= 25) return '#22c55e';
    return '#3b82f6';
});

// ── Copy address ─────────────────────────────────────────────────
const copied = ref(false);
let copyTimer: ReturnType<typeof setTimeout> | null = null;

async function copyAddress() {
    try {
        await navigator.clipboard.writeText(props.server.address);
    } catch {
        // Clipboard API needs a secure context; fall back to a temporary field.
        const field = document.createElement('textarea');
        field.value = props.server.address;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        try {
            document.execCommand('copy');
        } finally {
            document.body.removeChild(field);
        }
    }

    copied.value = true;
    if (copyTimer) clearTimeout(copyTimer);
    copyTimer = setTimeout(() => { copied.value = false; }, 1600);
}

onUnmounted(() => { if (copyTimer) clearTimeout(copyTimer); });

function toggleFavourite() {
    if (!page.props.auth?.user) { router.visit(route('login')); return; }
    const previous = favourited.value;
    favourited.value = !previous; // optimistic — rolled back onError
    router.post(route('servers.favourite', props.server.id), {}, {
        preserveScroll: true, preserveState: true,
        onError: () => { favourited.value = previous; },
    });
}
</script>

<template>
    <div class="hc-server-row group relative flex items-center gap-3 px-3 sm:px-4 py-2.5 border-b last:border-0 overflow-hidden
                transition-colors duration-300 ease-out"
        :class="dark ? 'border-zinc-800/50 hover:bg-white/[0.03]' : 'border-zinc-100 hover:bg-zinc-100/70'">

        <!-- Current map, bleeding in from the right and faded out so it
             stays behind the text. Decorative — the map name is in the row. -->
        <div v-if="server.row_image && !mapFailed"
            class="absolute inset-y-0 right-0 w-[55%] pointer-events-none hidden sm:block"
            aria-hidden="true">
            <!-- Timing lives with .hc-server-map in app.css, which also owns the
                 mask; a `transition` shorthand there outranks utilities, so
                 keeping both in sync from here would silently lose. -->
            <img :src="server.row_image" alt="" loading="lazy" decoding="async"
                class="hc-server-map w-full h-full object-cover transform-gpu
                       group-hover:scale-[1.06] motion-reduce:group-hover:scale-100"
                :class="dark ? 'opacity-[0.45] group-hover:opacity-[0.6]' : 'opacity-[0.22] group-hover:opacity-[0.32]'"
                @error="mapFailed = true" />
        </div>

        <!-- Accent edge in the game's colour -->
        <span class="absolute left-0 inset-y-0 w-[3px] transition-opacity"
            :style="{ background: gameColor(server.game_slug) }"
            :class="server.is_online ? 'opacity-100' : 'opacity-30'"
            aria-hidden="true" />

        <!-- Game icon with an online dot -->
        <div class="relative shrink-0 ml-1">
            <span class="block w-9 h-9 rounded-lg overflow-hidden">
                <GameIcon :slug="server.game_slug" :alt="server.game_name" />
            </span>
            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2"
                :class="[
                    server.is_online ? 'bg-emerald-500' : dark ? 'bg-zinc-600' : 'bg-zinc-400',
                    dark ? 'border-[#111113]' : 'border-white',
                ]"
                role="img"
                :aria-label="server.is_online ? t('home.status_online') : t('home.status_offline')"
                :title="server.is_online ? t('home.status_online') : t('home.status_offline')" />
        </div>

        <!-- Name + map -->
        <div class="relative min-w-0 flex-1">
            <Link :href="server.show_route"
                class="block text-[13.5px] font-bold truncate transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-600'">
                {{ server.name }}
            </Link>
            <p class="text-[11.5px] font-mono truncate mt-0.5 text-zinc-500">
                {{ server.map }}
                <span class="lg:hidden" :class="pingColor(server.ping, dark)"> · {{ server.ping }}ms</span>
            </p>
        </div>

        <!-- Address + copy -->
        <div class="relative hidden lg:flex items-center gap-1.5 shrink-0">
            <span class="text-[12.5px] font-mono tabular-nums"
                  :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ server.address }}</span>
            <button type="button"
                class="w-7 h-7 flex items-center justify-center rounded-md transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                :class="copied
                    ? (dark ? 'text-emerald-400' : 'text-emerald-800')
                    : dark ? 'text-zinc-500 hover:text-zinc-200 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-800 hover:bg-zinc-200/70'"
                :aria-label="t('home.copy_address', { address: server.address })"
                :title="copied ? t('home.copied') : t('home.copy_address', { address: server.address })"
                @click="copyAddress">
                <Check v-if="copied" :size="14" :stroke-width="2.4" aria-hidden="true" />
                <Copy v-else :size="14" :stroke-width="1.9" aria-hidden="true" />
            </button>
        </div>

        <!-- Ping -->
        <span class="relative hidden lg:block text-[12.5px] font-mono tabular-nums w-14 text-right shrink-0"
              :class="pingColor(server.ping, dark)">{{ server.ping }}ms</span>

        <!-- Connect -->
        <a :href="server.connect_route"
            class="relative shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
            :class="dark ? 'text-zinc-500 hover:text-emerald-400 hover:bg-emerald-500/10' : 'text-zinc-500 hover:text-emerald-800 hover:bg-emerald-500/10'"
            :aria-label="t('home.connect_to', { name: server.name })"
            :title="t('home.connect_to', { name: server.name })">
            <Play :size="15" :stroke-width="2" fill="currentColor" aria-hidden="true" />
        </a>

        <!-- Players pill, coloured by how full the server is -->
        <span class="relative shrink-0 px-2.5 py-1 rounded-md text-[12px] font-bold tabular-nums text-white min-w-[62px] text-center"
            :style="{ backgroundColor: fillColor }"
            :title="t('home.filter_tooltip', { players: server.players, max: server.max_players, percent: fillPercent })">
            {{ server.players }}/{{ server.max_players }}
        </span>

        <!-- Favourite -->
        <button type="button"
            class="relative shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
            :class="favourited
                ? 'text-amber-400'
                : dark ? 'text-zinc-500 hover:text-amber-400 hover:bg-white/[0.06]' : 'text-zinc-400 hover:text-amber-500 hover:bg-zinc-200/70'"
            :aria-pressed="favourited"
            :aria-label="t(favourited ? 'home.unfavourite_server' : 'home.favourite_server', { name: server.name })"
            :title="t(favourited ? 'home.unfavourite_server' : 'home.favourite_server', { name: server.name })"
            @click="toggleFavourite">
            <Star :size="15" :stroke-width="1.9" :fill="favourited ? 'currentColor' : 'none'" aria-hidden="true" />
        </button>
    </div>
</template>
