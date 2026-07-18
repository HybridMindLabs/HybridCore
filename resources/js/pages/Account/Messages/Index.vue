<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MessageSquare, Plus, X } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import AccountPage from '@/components/Account/AccountPage.vue';
import { computed, ref } from 'vue';

interface Other { id: number; username: string; display_name: string; avatar: string | null }
interface LastMsg { body: string; is_mine: boolean; at: string }
interface Conv { id: number; other: Other; last_message: LastMsg | null; unread: number }

const props = defineProps<{ conversations: Conv[]; unreadNotifications?: number; unreadMessages?: number }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const showNewDm = ref(false);
const newDmForm = useForm({ username: '' });

const totalUnread = computed(() => props.conversations.reduce((sum, c) => sum + c.unread, 0));

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}

function conversationName(conv: Conv): string {
    return conv.other.display_name || conv.other.username;
}

function startConversation() {
    newDmForm.post(route('account.messages.start'), {
        onSuccess: () => {
            showNewDm.value = false;
            newDmForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="t('account.msg_title')" />

    <AccountPage
        active-tab="messages"
        :section="t('account.msg_title')"
        :unread-notifications="unreadNotifications"
        :unread-messages="unreadMessages"
    >
        <template #subtitle>{{ t('account.msg_subtitle') }}</template>

        <div class="rounded-2xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">

            <div class="px-5 sm:px-6 py-4 border-b flex items-start justify-between gap-4 flex-wrap"
                :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ t('account.msg_title') }}
                        </h2>
                        <span v-if="totalUnread"
                            class="px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-blue-600 text-white tabular-nums"
                            aria-live="polite">
                            {{ t('account.msg_unread_badge', { count: totalUnread }) }}
                        </span>
                    </div>
                    <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                        {{ t('account.msg_subtitle') }}
                    </p>
                </div>

                <button type="button"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-[12px] font-bold transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="showNewDm
                        ? (dark ? 'border border-zinc-700 text-zinc-300 hover:text-white' : 'border border-zinc-300 text-zinc-700 hover:text-zinc-900')
                        : 'bg-blue-600 text-white hover:bg-blue-500'"
                    :aria-expanded="showNewDm"
                    aria-controls="new-dm-form"
                    @click="showNewDm = !showNewDm">
                    <component :is="showNewDm ? X : Plus" :size="13" :stroke-width="2.5" aria-hidden="true" />
                    {{ showNewDm ? t('account.msg_cancel') : t('account.msg_new') }}
                </button>
            </div>

            <!-- New conversation -->
            <div v-if="showNewDm" id="new-dm-form" class="px-5 sm:px-6 py-4 border-b"
                :class="dark ? 'border-zinc-800/60 bg-zinc-900/40' : 'border-zinc-200 bg-zinc-50'">
                <label for="dm-username" class="block text-[11px] font-bold uppercase tracking-wider"
                    :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                    {{ t('account.msg_username_label') }}
                </label>
                <p id="dm-username-hint" class="text-[11.5px] mt-0.5 mb-2"
                   :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                    {{ t('account.msg_new_hint') }}
                </p>

                <form class="flex gap-2" @submit.prevent="startConversation">
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-[13px] pointer-events-none"
                            :class="dark ? 'text-zinc-500' : 'text-zinc-500'" aria-hidden="true">@</span>
                        <input
                            id="dm-username"
                            v-model="newDmForm.username"
                            type="text"
                            autofocus
                            autocomplete="off"
                            :placeholder="t('account.msg_username_placeholder')"
                            class="w-full pl-7 pr-4 py-2.5 rounded-xl border text-[13px] font-mono transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                            :class="dark
                                ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50'
                                : 'border-zinc-300 bg-white text-zinc-900 placeholder:text-zinc-500 focus:border-blue-500/60'"
                            :aria-invalid="newDmForm.errors.username ? 'true' : undefined"
                            :aria-describedby="newDmForm.errors.username ? 'dm-username-error' : 'dm-username-hint'"
                        />
                    </div>
                    <button type="submit" :disabled="newDmForm.processing || !newDmForm.username.trim()"
                        class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-[13px] font-bold transition disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                        {{ t('account.msg_start') }}
                    </button>
                </form>

                <p v-if="newDmForm.errors.username" id="dm-username-error" role="alert"
                   class="text-red-500 text-[12px] font-semibold mt-1.5">{{ newDmForm.errors.username }}</p>
            </div>

            <!-- Empty state -->
            <div v-if="conversations.length === 0" class="flex flex-col items-center text-center px-6 py-14">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                    :class="dark ? 'bg-zinc-900 text-zinc-700' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <MessageSquare :size="26" :stroke-width="1.4" />
                </span>
                <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                    {{ t('account.msg_empty') }}
                </p>
                <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.msg_empty_hint') }}
                </p>
            </div>

            <!-- Conversations -->
            <ul v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-200'"
                :aria-label="t('account.msg_list_label')">
                <li v-for="conv in conversations" :key="conv.id">
                    <Link :href="route('account.messages.show', conv.id)"
                        class="flex items-center gap-3 px-5 py-4 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                        :class="dark ? 'hover:bg-zinc-900/40' : 'hover:bg-zinc-100/70'"
                        :aria-label="t('account.msg_open_conversation', { name: conversationName(conv) })">

                        <div class="w-11 h-11 rounded-xl overflow-hidden shrink-0">
                            <img v-if="conv.other.avatar" :src="conv.other.avatar" alt="" loading="lazy"
                                class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center font-black text-white text-[15px] uppercase"
                                :style="{ backgroundColor: avatarBg(conv.other.username) }" aria-hidden="true">
                                {{ conv.other.username[0] }}
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[13.5px] font-bold truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                    {{ conversationName(conv) }}
                                </p>
                                <span class="text-[11px] shrink-0" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                    {{ conv.last_message?.at }}
                                </span>
                            </div>
                            <p v-if="conv.last_message" class="text-[12.5px] truncate mt-0.5"
                                :class="conv.unread > 0
                                    ? (dark ? 'text-zinc-200 font-semibold' : 'text-zinc-900 font-semibold')
                                    : (dark ? 'text-zinc-500' : 'text-zinc-600')">
                                <span v-if="conv.last_message.is_mine" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                    {{ t('account.msg_you_prefix') }}
                                </span>
                                {{ conv.last_message.body }}
                            </p>
                        </div>

                        <span v-if="conv.unread > 0"
                            class="shrink-0 min-w-[20px] h-5 px-1.5 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center tabular-nums"
                            :aria-label="t('account.msg_unread_badge', { count: conv.unread })">
                            {{ conv.unread > 9 ? '9+' : conv.unread }}
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </AccountPage>
</template>
