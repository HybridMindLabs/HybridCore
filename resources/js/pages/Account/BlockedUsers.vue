<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Ban, UserX } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { computed } from 'vue';

interface BlockEntry {
    id: number;
    user: { id: number; username: string; display_name: string; avatar: string | null };
    blocked_at: string;
}

defineProps<{ blocks: BlockEntry[] }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

function unblock(userId: number) {
    router.delete(route('account.unblock', userId), { preserveScroll: true });
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}
</script>

<template>
    <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
        <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Blocked Users</p>
            <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Blocked users cannot send you messages.</p>
        </div>

        <div v-if="blocks.length === 0" class="p-12 text-center">
            <Ban :size="28" :stroke-width="1.5" class="mx-auto mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
            <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No blocked users</p>
        </div>

        <div v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-100'">
            <div v-for="b in blocks" :key="b.id" class="flex items-center gap-3 px-5 py-4">
                <div class="w-9 h-9 rounded-xl overflow-hidden shrink-0">
                    <img v-if="b.user.avatar" :src="b.user.avatar" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center font-black text-white text-[13px] uppercase"
                        :style="{ backgroundColor: avatarBg(b.user.username) }">{{ b.user.username[0] }}</div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ b.user.display_name || b.user.username }}</p>
                    <p class="text-[11px] font-mono" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">@{{ b.user.username }} · Blocked {{ b.blocked_at }}</p>
                </div>
                <button type="button"
                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-semibold transition"
                    :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-600 hover:border-zinc-300'"
                    @click="unblock(b.user.id)">
                    <UserX :size="11" :stroke-width="2" /> Unblock
                </button>
            </div>
        </div>
    </div>
</template>
