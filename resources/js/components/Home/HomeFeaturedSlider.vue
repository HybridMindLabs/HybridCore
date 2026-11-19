<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronLeft, ChevronRight, Pause, Play, Wifi } from '@lucide/vue';
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { gameColor, pingColor, type HomeServer } from './types';

const props = defineProps<{ servers: HomeServer[] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const featured = computed(() => props.servers.filter(s => s.is_online).slice(0, 6));
const failedMapImages = reactive(new Set<number>());
const slideIndex = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

// WCAG 2.2.2: content that moves on its own for more than five seconds needs a
// way to stop it. Hover and keyboard focus pause it, there is an explicit
// toggle, and it never auto-starts for people who asked for reduced motion.
const reducedMotion = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

// The user's pause/resume choice is a deliberate preference — resetting it to
// "playing" on every reload undoes the very thing they just clicked.
const AUTOPLAY_STORAGE_KEY = 'hc-home-autoplay';
const storedAutoplay = typeof window !== 'undefined' ? window.localStorage.getItem(AUTOPLAY_STORAGE_KEY) : null;
const autoplay = ref(!reducedMotion && storedAutoplay !== '0');
const hovered = ref(false);

function nextSlide() { slideIndex.value = (slideIndex.value + 1) % featured.value.length; }
function prevSlide() { slideIndex.value = (slideIndex.value - 1 + featured.value.length) % featured.value.length; }

function resetTimer() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
    if (!autoplay.value || hovered.value || featured.value.length < 2) return;
    timer = setInterval(nextSlide, 5000);
}

// A refresh with fewer online servers must not leave the index past the end.
watch(featured, (list) => { if (slideIndex.value >= list.length) slideIndex.value = 0; });
watch([autoplay, hovered, featured], resetTimer);
watch(autoplay, (value) => { window.localStorage.setItem(AUTOPLAY_STORAGE_KEY, value ? '1' : '0'); });
onMounted(resetTimer);
onUnmounted(() => { if (timer) clearInterval(timer); });

// ── Touch swipe ──────────────────────────────────────────────────
const SWIPE_THRESHOLD = 40;
let touchStartX = 0;

function onTouchStart(e: TouchEvent) {
    touchStartX = e.changedTouches[0].clientX;
    hovered.value = true;
}
function onTouchEnd(e: TouchEvent) {
    const deltaX = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(deltaX) >= SWIPE_THRESHOLD) {
        if (deltaX < 0) nextSlide(); else prevSlide();
    }
    hovered.value = false;
}

const controlClass = computed(() => dark.value
    ? 'border-zinc-800 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700'
    : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300');
</script>

<template>
    <section :aria-label="t('home.featured_servers')"
        aria-roledescription="carousel"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
        @focusin="hovered = true"
        @focusout="hovered = false">

        <div class="flex items-center justify-between gap-2 mb-2.5">
            <div class="min-w-0">
                <h3 class="text-[11px] font-black uppercase tracking-widest"
                    :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                    {{ t('home.featured_servers') }}
                </h3>
                <p class="text-[11px] mt-0.5 leading-snug text-zinc-500">
                    {{ t('home.featured_hint') }}
                </p>
            </div>

            <div v-if="featured.length > 1" class="flex items-center gap-1 shrink-0">
                <button type="button" @click="autoplay = !autoplay"
                    class="w-7 h-7 flex items-center justify-center rounded-lg border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="controlClass"
                    :aria-label="autoplay ? t('home.pause_rotation') : t('home.resume_rotation')"
                    :title="autoplay ? t('home.pause_rotation') : t('home.resume_rotation')">
                    <Pause v-if="autoplay" :size="12" :stroke-width="2.2" aria-hidden="true" />
                    <Play v-else :size="12" :stroke-width="2.2" aria-hidden="true" />
                </button>
                <button type="button" @click="prevSlide"
                    class="w-7 h-7 flex items-center justify-center rounded-lg border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="controlClass"
                    :aria-label="t('home.previous_server')" :title="t('home.previous_server')">
                    <ChevronLeft :size="13" :stroke-width="2.2" aria-hidden="true" />
                </button>
                <button type="button" @click="nextSlide"
                    class="w-7 h-7 flex items-center justify-center rounded-lg border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="controlClass"
                    :aria-label="t('home.next_server')" :title="t('home.next_server')">
                    <ChevronRight :size="13" :stroke-width="2.2" aria-hidden="true" />
                </button>
            </div>
        </div>

        <!-- Cards -->
        <div v-if="featured.length" class="relative" style="min-height: 340px"
            aria-live="polite" :aria-atomic="false"
            @touchstart.passive="onTouchStart" @touchend.passive="onTouchEnd">
            <template v-for="(server, i) in featured" :key="server.id">
                <Transition
                    enter-active-class="transition-all duration-500 ease-out"
                    enter-from-class="opacity-0 translate-y-3"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-300 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-3"
                >
                    <div v-if="i === slideIndex"
                        class="absolute inset-0 rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_2px_12px_rgba(0,0,0,0.08)]'"
                    >
                        <!-- Header: map screenshot when available, game-colored glow otherwise -->
                        <div class="relative h-[120px] overflow-hidden flex items-end p-4"
                            :style="server.map_image && !failedMapImages.has(server.id)
                                ? undefined
                                : `background: linear-gradient(135deg, ${gameColor(server.game_slug)}18 0%, ${gameColor(server.game_slug)}06 100%)`">
                            <img v-if="server.map_image && !failedMapImages.has(server.id)"
                                :src="server.map_image" :alt="server.map" decoding="async"
                                class="absolute inset-0 w-full h-full object-cover"
                                @error="failedMapImages.add(server.id)" />
                            <div v-else class="absolute -top-8 -right-8 w-40 h-40 rounded-full blur-3xl opacity-25"
                                :style="`background: ${gameColor(server.game_slug)}`" />
                            <div class="absolute inset-0"
                                :class="dark ? 'bg-gradient-to-t from-[#111113] via-[#111113]/40 to-transparent' : 'bg-gradient-to-t from-white via-white/40 to-transparent'" />

                            <!-- Game icon -->
                            <div class="absolute top-3.5 right-4 w-14 h-14 rounded-xl overflow-hidden border"
                                :class="dark ? 'border-zinc-800' : 'border-zinc-200 shadow-sm'">
                                <GameIcon :slug="server.game_slug" :alt="server.game_name" />
                            </div>

                            <!-- Status -->
                            <div class="relative z-10">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="server.is_online ? 'bg-emerald-400' : 'bg-zinc-500'" />
                                    <span class="text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                        {{ server.is_online ? t('home.status_online') : t('home.status_offline') }}
                                    </span>
                                </div>
                                <p class="text-[11px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ server.game_name }}</p>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="p-4">
                            <Link :href="server.show_route"
                                class="block text-[15px] font-black tracking-tight truncate mb-4 transition-colors"
                                :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-600'">
                                {{ server.name }}
                            </Link>

                            <div class="flex flex-col gap-2.5 mb-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('home.col_map') }}</span>
                                    <span class="text-[12px] font-mono truncate max-w-[160px]" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ server.map }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-[10px] font-bold uppercase tracking-widest shrink-0" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('home.col_players') }}</span>
                                    <div class="flex items-center gap-2 flex-1 justify-end">
                                        <div class="h-1 flex-1 max-w-[80px] rounded-full overflow-hidden" :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'">
                                            <div class="h-full rounded-full"
                                                :style="`width:${server.max_players > 0 ? Math.round((server.players / server.max_players) * 100) : 0}%; background:${gameColor(server.game_slug)}`" />
                                        </div>
                                        <span class="text-[12px] font-mono shrink-0" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ server.players }}/{{ server.max_players }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('home.col_ping') }}</span>
                                    <span class="text-[12px] font-mono" :class="pingColor(server.ping, dark)">{{ server.ping }}ms</span>
                                </div>
                            </div>

                            <!-- Connect -->
                            <a :href="server.connect_route"
                                class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-bold border transition-colors"
                                :style="`color: ${gameColor(server.game_slug)}; border-color: ${gameColor(server.game_slug)}30; background: ${gameColor(server.game_slug)}12`"
                            >
                                {{ t('home.connect') }} <ArrowRight :size="13" :stroke-width="2.2" />
                            </a>
                        </div>
                    </div>
                </Transition>
            </template>
        </div>

        <!--
            The dot is 6px, which is far under the 24x24 minimum for
            a touch target. The button carries the full 24x24 and the
            dot is drawn inside it, so the target grows without the
            indicator getting heavier. That does space the dots out —
            24px apart rather than 12 — which is the cost of them
            being reliably tappable.
        -->
        <div v-if="featured.length > 1" class="flex items-center justify-center pt-1">
            <button v-for="(s, i) in featured" :key="s.id" type="button"
                class="group grid place-items-center w-6 h-6 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                :aria-label="t('home.go_to_server', { name: s.name })"
                :aria-current="i === slideIndex ? 'true' : undefined"
                :title="s.name"
                @click="slideIndex = i"
            >
                <span class="rounded-full transition-all duration-300"
                    :class="i === slideIndex
                        ? 'w-5 h-1.5 bg-blue-500'
                        : dark ? 'w-1.5 h-1.5 bg-zinc-700 group-hover:bg-zinc-500' : 'w-1.5 h-1.5 bg-zinc-300 group-hover:bg-zinc-400'"
                    aria-hidden="true"
                />
            </button>
        </div>

        <div v-if="!featured.length"
            class="rounded-2xl border px-6 py-10 text-center"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
            <span class="mx-auto mb-3 w-11 h-11 rounded-2xl flex items-center justify-center"
                :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                <Wifi :size="20" :stroke-width="1.5" />
            </span>
            <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">
                {{ t('home.no_servers_online') }}
            </p>
            <p class="text-[11.5px] mt-1 text-zinc-500">
                {{ t('home.no_servers_online_hint') }}
            </p>
        </div>
    </section>
</template>
