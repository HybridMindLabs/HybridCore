<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { initial, type RecentMember } from './types';

defineProps<{ members: RecentMember[] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <div class="hc-reveal rounded-xl border overflow-hidden" style="animation-delay:0.08s"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
        <div class="px-4 py-3 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <div class="flex items-center gap-2">
                <UserPlus :size="13" :stroke-width="1.8" :class="dark ? 'text-blue-400' : 'text-blue-500'" />
                <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">
                    {{ t('home.recently_joined') }}
                </p>
            </div>
            <p class="text-[11px] mt-0.5 leading-snug" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                {{ t('home.recently_joined_hint') }}
            </p>
        </div>
        <div class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-100'">
            <!-- A member without a username has no profile page; render a plain
                 row instead of a link to "#" that jumps to the top. -->
            <component :is="m.username ? Link : 'div'" v-for="m in members" :key="m.id"
                :href="m.username ? route('profile.show', { username: m.username }) : undefined"
                class="flex items-center gap-2.5 px-4 py-2.5 transition-colors"
                :class="m.username ? (dark ? 'hover:bg-white/[0.04]' : 'hover:bg-zinc-50') : ''">
                <span class="w-6 h-6 rounded-lg overflow-hidden shrink-0">
                    <img v-if="m.avatar" :src="m.avatar" alt="" loading="lazy" decoding="async" class="w-full h-full object-cover" />
                    <span v-else class="w-full h-full flex items-center justify-center text-[9px] font-black text-white uppercase bg-blue-500">
                        {{ initial(m.name) }}
                    </span>
                </span>
                <p class="flex-1 min-w-0 text-[12px] leading-snug" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                    <span class="font-bold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ m.name }}</span>
                    <span class="text-[10px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'"> · {{ m.joined_at }}</span>
                </p>
            </component>
        </div>
    </div>
</template>
