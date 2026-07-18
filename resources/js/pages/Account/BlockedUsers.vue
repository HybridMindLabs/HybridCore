<script setup lang="ts">
import { router, Link } from '@inertiajs/vue3';
import { Ban, UserX, Info } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';

interface BlockEntry {
    id: number;
    user: { id: number; username: string; display_name: string; avatar: string | null };
    blocked_at: string;
}

defineProps<{ blocks: BlockEntry[] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const pending = ref<number | null>(null);

function unblock(userId: number) {
    if (pending.value) return;
    pending.value = userId;
    router.delete(route('account.unblock', userId), {
        preserveScroll: true,
        onFinish: () => { pending.value = null; },
    });
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="rounded-2xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">

            <div class="px-5 sm:px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                <div class="flex items-center gap-2">
                    <h2 class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('account.blk_title') }}
                    </h2>
                    <span v-if="blocks.length"
                        class="px-2 py-0.5 rounded-full text-[10.5px] font-bold tabular-nums"
                        :class="dark ? 'bg-zinc-800 text-zinc-300' : 'bg-zinc-200 text-zinc-700'">
                        {{ t('account.blk_count', { count: blocks.length }) }}
                    </span>
                </div>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                    {{ t('account.blk_subtitle') }}
                </p>
            </div>

            <div v-if="blocks.length === 0" class="flex flex-col items-center text-center px-6 py-14">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                    :class="dark ? 'bg-zinc-900 text-zinc-700' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <Ban :size="26" :stroke-width="1.4" />
                </span>
                <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                    {{ t('account.blk_empty') }}
                </p>
                <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.blk_empty_hint') }}
                </p>
            </div>

            <ul v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-200'"
                :aria-label="t('account.blk_list_label')">
                <li v-for="b in blocks" :key="b.id"
                    class="flex items-center gap-3 px-5 py-4 transition-colors"
                    :class="dark ? 'hover:bg-white/[0.02]' : 'hover:bg-zinc-100/60'">

                    <div class="w-10 h-10 rounded-xl overflow-hidden shrink-0">
                        <img v-if="b.user.avatar" :src="b.user.avatar" alt="" loading="lazy"
                            class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center font-black text-white text-[14px] uppercase"
                            :style="{ backgroundColor: avatarBg(b.user.username) }" aria-hidden="true">
                            {{ b.user.username[0] }}
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <!-- The name used to be plain text, so there was no way to
                             check who this actually was before unblocking. -->
                        <Link :href="route('profile.show', { username: b.user.username })"
                            class="block text-[13.5px] font-bold truncate transition-colors rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-700'"
                            :aria-label="t('account.blk_view_profile', { name: b.user.display_name || b.user.username })">
                            {{ b.user.display_name || b.user.username }}
                        </Link>
                        <p class="flex items-center gap-2 text-[11.5px] mt-0.5 flex-wrap"
                           :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                            <span class="font-mono">@{{ b.user.username }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ t('account.blk_blocked_on', { date: b.blocked_at }) }}</span>
                        </p>
                    </div>

                    <button type="button"
                        class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border text-[12px] font-bold transition disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600' : 'border-zinc-300 text-zinc-700 hover:text-zinc-900 hover:border-zinc-400'"
                        :disabled="pending === b.user.id"
                        :aria-label="t('account.blk_unblock_user', { name: b.user.display_name || b.user.username })"
                        @click="unblock(b.user.id)">
                        <UserX :size="12" :stroke-width="2" aria-hidden="true" />
                        {{ t('account.blk_unblock') }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- Blocking is easy to misread as "they disappear". Say what it does. -->
        <div class="flex items-start gap-3.5 rounded-2xl border px-5 py-4"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
            <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                :class="dark ? 'bg-blue-500/15 text-blue-400' : 'bg-blue-500/10 text-blue-700'" aria-hidden="true">
                <Info :size="16" :stroke-width="1.9" />
            </span>
            <div class="min-w-0">
                <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-900'">
                    {{ t('account.blk_note_title') }}
                </p>
                <p class="text-[12.5px] leading-relaxed mt-1" :class="dark ? 'text-zinc-400' : 'text-zinc-700'">
                    {{ t('account.blk_note_body') }}
                </p>
            </div>
        </div>
    </div>
</template>
