<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Star, Wifi, WifiOff, Users, Play, Map } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import AccountPage from '@/components/Account/AccountPage.vue';
import { computed, reactive, ref } from 'vue';

interface Server {
    id: number; name: string; game: string | null; game_slug: string | null; game_icon: string | null;
    address: string; map: string | null; map_image: string | null;
    players: number | null; max_players: number | null;
    online: boolean; show_url: string | null; connect_url: string | null;
}

const props = defineProps<{ servers: Server[]; unreadNotifications?: number; unreadMessages?: number }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const failedMapImages = reactive(new Set<number>());

/**
 * Removing a favourite happens here rather than only in the server browser —
 * this is the page you open to prune the list, so the action belongs on it.
 * Rows disappear optimistically and come back if the request fails.
 */
const removed = reactive(new Set<number>());
const pending = ref<number | null>(null);

const visibleServers = computed(() => props.servers.filter(s => !removed.has(s.id)));

async function removeFavourite(server: Server) {
    if (pending.value) return;

    pending.value = server.id;
    removed.add(server.id);

    try {
        const res = await fetch(route('servers.favourite', server.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                Accept: 'application/json',
            },
        });
        if (!res.ok) throw new Error('request failed');
    } catch {
        removed.delete(server.id);
    } finally {
        pending.value = null;
    }
}
</script>

<template>
    <Head :title="t('account.fav_title')" />

    <AccountPage
        active-tab="favorites"
        :section="t('account.fav_title')"
        :unread-notifications="unreadNotifications"
        :unread-messages="unreadMessages"
    >
        <template #subtitle>{{ t('account.fav_subtitle') }}</template>

        <div class="rounded-2xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">

            <div class="px-5 sm:px-6 py-4 border-b flex items-start justify-between gap-4 flex-wrap"
                :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ t('account.fav_title') }}
                        </h2>
                        <span v-if="visibleServers.length"
                            class="px-2 py-0.5 rounded-full text-[10.5px] font-bold tabular-nums"
                            :class="dark ? 'bg-zinc-800 text-zinc-300' : 'bg-zinc-200 text-zinc-500'">
                            {{ t('account.fav_count', { count: visibleServers.length }) }}
                        </span>
                    </div>
                    <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ t('account.fav_subtitle') }}
                    </p>
                </div>

                <Link :href="route('servers.index')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border text-[12px] font-bold transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600' : 'border-zinc-300 text-zinc-500 hover:text-zinc-900 hover:border-zinc-400'">
                    {{ t('account.fav_browse') }}
                </Link>
            </div>

            <!-- Empty -->
            <div v-if="visibleServers.length === 0" class="flex flex-col items-center text-center px-6 py-14">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                    :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <Star :size="26" :stroke-width="1.4" />
                </span>
                <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">
                    {{ t('account.fav_empty') }}
                </p>
                <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.fav_empty_hint') }}
                </p>
                <Link :href="route('servers.index')"
                    class="mt-5 inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-[13px] font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                    {{ t('account.fav_browse') }}
                </Link>
            </div>

            <!-- List -->
            <ul v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-200'"
                :aria-label="t('account.fav_list_label')">
                <li v-for="s in visibleServers" :key="s.id"
                    class="flex items-center gap-4 px-5 py-4 transition-colors"
                    :class="dark ? 'hover:bg-white/[0.02]' : 'hover:bg-zinc-100/60'">

                    <!-- Map thumbnail, falling back to the game cover -->
                    <div class="w-16 h-10 rounded-lg overflow-hidden shrink-0 border relative"
                        :class="dark ? 'border-zinc-800 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'"
                        aria-hidden="true">
                        <img v-if="s.map_image && !failedMapImages.has(s.id)" :src="s.map_image" alt=""
                            loading="lazy" class="w-full h-full object-cover"
                            @error="failedMapImages.add(s.id)" />
                        <img v-else-if="s.game_icon" :src="s.game_icon" alt="" loading="lazy"
                            class="w-full h-full object-contain p-2" />
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <component :is="s.show_url ? 'a' : 'span'" :href="s.show_url ?? undefined"
                            class="block text-[13.5px] font-bold truncate transition-colors rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="s.show_url
                                ? (dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-700')
                                : (dark ? 'text-zinc-100' : 'text-zinc-900')"
                            :aria-label="s.show_url ? t('account.fav_open', { name: s.name }) : undefined">
                            {{ s.name }}
                        </component>

                        <div class="flex items-center gap-3 mt-1 flex-wrap">
                            <span class="flex items-center gap-1 text-[11.5px] font-semibold"
                                :class="s.online
                                    ? (dark ? 'text-emerald-400' : 'text-emerald-800')
                                    : (dark ? 'text-zinc-500' : 'text-zinc-500')"
                                :title="s.online ? undefined : t('account.fav_offline_hint')">
                                <component :is="s.online ? Wifi : WifiOff" :size="11" :stroke-width="2" aria-hidden="true" />
                                {{ s.online ? t('account.fav_online') : t('account.fav_offline') }}
                            </span>
                            <span v-if="s.players !== null" class="flex items-center gap-1 text-[11.5px] tabular-nums"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                                <Users :size="11" :stroke-width="2" aria-hidden="true" />
                                {{ s.players }}/{{ s.max_players }}
                            </span>
                            <span v-if="s.game" class="text-[11.5px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ s.game }}</span>
                            <span v-if="s.map" class="flex items-center gap-1 text-[11.5px] font-mono truncate"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                <Map :size="10" :stroke-width="1.9" aria-hidden="true" />{{ s.map }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <a v-if="s.connect_url" :href="s.connect_url"
                            class="w-9 h-9 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'text-zinc-500 hover:text-emerald-400 hover:bg-emerald-500/10' : 'text-zinc-500 hover:text-emerald-800 hover:bg-emerald-500/10'"
                            :aria-label="t('account.fav_connect_to', { name: s.name })"
                            :title="t('account.fav_connect_to', { name: s.name })">
                            <Play :size="15" :stroke-width="2" fill="currentColor" aria-hidden="true" />
                        </a>

                        <button type="button"
                            class="w-9 h-9 flex items-center justify-center rounded-lg text-amber-400 transition disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50"
                            :class="dark ? 'hover:bg-white/[0.06] hover:text-zinc-400' : 'hover:bg-zinc-200/70 hover:text-zinc-500'"
                            :disabled="pending === s.id"
                            :aria-label="t('account.fav_remove', { name: s.name })"
                            :title="t('account.fav_remove', { name: s.name })"
                            @click="removeFavourite(s)">
                            <Star :size="15" :stroke-width="1.9" fill="currentColor" aria-hidden="true" />
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </AccountPage>
</template>
