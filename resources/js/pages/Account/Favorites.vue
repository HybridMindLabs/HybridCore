<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Star, Wifi, WifiOff, Users } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import AccountPage from '@/components/Account/AccountPage.vue';
import { computed, reactive } from 'vue';

interface Server {
    id: number; name: string; game: string | null; game_slug: string | null; game_icon: string | null;
    map: string | null; map_image: string | null; players: number | null; max_players: number | null;
    online: boolean; connect_url: string | null;
}

defineProps<{ servers: Server[]; unreadNotifications?: number; unreadMessages?: number }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const failedMapImages = reactive(new Set<number>());
</script>

<template>
    <Head title="Favorite Servers" />

    <AccountPage active-tab="favorites" section="Favorite Servers" :unread-notifications="unreadNotifications" :unread-messages="unreadMessages">
        <template #subtitle>Your bookmarked servers for quick access.</template>
        <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
            <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Favorite Servers</p>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Your bookmarked servers for quick access.</p>
            </div>

            <div v-if="servers.length === 0" class="p-12 text-center">
                <Star :size="28" :stroke-width="1.5" class="mx-auto mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No favorites yet</p>
                <p class="text-[12px] mt-1" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Bookmark servers from the server browser to see them here.</p>
            </div>

            <div v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-100'">
                <div v-for="s in servers" :key="s.id" class="flex items-center gap-4 px-5 py-4">
                    <!-- Map thumbnail (falls back to game icon) -->
                    <div class="w-16 h-9 rounded-lg overflow-hidden shrink-0 border relative"
                        :class="dark ? 'border-zinc-800 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'">
                        <img v-if="s.map_image && !failedMapImages.has(s.id)" :src="s.map_image" :alt="s.map ?? ''" class="w-full h-full object-cover"
                            @error="failedMapImages.add(s.id)" />
                        <img v-else-if="s.game_icon" :src="s.game_icon" class="w-full h-full object-contain p-2" />
                    </div>
                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-bold truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ s.name }}</p>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="flex items-center gap-1 text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                <component :is="s.online ? Wifi : WifiOff" :size="10" :stroke-width="2" :class="s.online ? 'text-emerald-400' : 'text-zinc-600'" />
                                {{ s.online ? 'Online' : 'Offline' }}
                            </span>
                            <span v-if="s.players !== null" class="flex items-center gap-1 text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                <Users :size="10" :stroke-width="2" />
                                {{ s.players }}/{{ s.max_players }}
                            </span>
                            <span v-if="s.game" class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ s.game }}</span>
                            <span v-if="s.map" class="text-[11px] font-mono truncate" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ s.map }}</span>
                        </div>
                    </div>
                    <!-- Connect -->
                    <a v-if="s.connect_url" :href="s.connect_url"
                        class="shrink-0 px-3 py-1.5 rounded-lg border text-[11px] font-bold transition"
                        :class="dark ? 'border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/10' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'">
                        Connect
                    </a>
                </div>
            </div>
        </div>
    </AccountPage>
</template>
