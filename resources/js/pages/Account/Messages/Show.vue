<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { Send, Trash2, ArrowLeft } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import AccountPage from '@/components/Account/AccountPage.vue';
import { computed, ref, nextTick, onMounted, onUnmounted } from 'vue';

interface Other { id: number; username: string; display_name: string; avatar: string | null }
interface Msg { id: number; body: string | null; deleted: boolean; is_mine: boolean; at: string; at_human: string }

const props = defineProps<{
    conversation: { id: number; other: Other };
    messages: { data: Msg[]; meta: any };
    unreadNotifications?: number;
    unreadMessages?: number;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const otherName = computed(() =>
    props.conversation.other.display_name || props.conversation.other.username);

const sendForm = useForm({ body: '' });
const messagesEnd = ref<HTMLElement | null>(null);

// ── Typing indicator (Reverb whisper — no server round-trip) ──────────────
const otherTyping = ref(false);
let typingChannel: ReturnType<typeof window.Echo.private> | null = null;
let otherTypingTimeout: ReturnType<typeof setTimeout> | null = null;
let lastWhisperAt = 0;

function notifyTyping() {
    const now = Date.now();
    if (now - lastWhisperAt < 2000) return; // throttle — at most once every 2s
    lastWhisperAt = now;
    typingChannel?.whisper('typing', {});
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}

function send() {
    if (!sendForm.body.trim()) return;
    sendForm.post(route('account.messages.send', props.conversation.id), {
        onSuccess: () => {
            sendForm.reset('body');
            nextTick(() => messagesEnd.value?.scrollIntoView({ behavior: 'smooth' }));
        },
    });
}

function deleteMessage(msgId: number) {
    router.delete(route('account.messages.delete', { conversation: props.conversation.id, message: msgId }), {
        preserveScroll: true,
    });
}

onMounted(() => {
    nextTick(() => messagesEnd.value?.scrollIntoView());

    const Echo = (window as Record<string, unknown>).Echo as typeof window.Echo | undefined;
    if (Echo) {
        typingChannel = Echo.private(`conversation.${props.conversation.id}`);
        typingChannel.listenForWhisper('typing', () => {
            otherTyping.value = true;
            if (otherTypingTimeout) clearTimeout(otherTypingTimeout);
            otherTypingTimeout = setTimeout(() => { otherTyping.value = false; }, 3000);
        });
    }
});

onUnmounted(() => {
    if (otherTypingTimeout) clearTimeout(otherTypingTimeout);
    const Echo = (window as Record<string, unknown>).Echo as typeof window.Echo | undefined;
    Echo?.leave(`conversation.${props.conversation.id}`);
});
</script>

<template>
    <Head :title="conversation.other.display_name || conversation.other.username" />

    <AccountPage
        active-tab="messages"
        :section="t('account.msg_title')"
        :unread-notifications="unreadNotifications"
        :unread-messages="unreadMessages"
    >
        <template #subtitle>{{ t('account.msg_conversation_with', { name: otherName }) }}</template>
    <div class="rounded-2xl border overflow-hidden flex flex-col" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
        <!-- Header -->
        <div class="px-5 py-4 border-b flex items-center gap-3" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <a :href="route('account.messages.index')"
                class="w-8 h-8 rounded-lg flex items-center justify-center transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                :class="dark ? 'text-zinc-500 hover:text-zinc-100 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200/70'"
                :aria-label="t('account.msg_back')" :title="t('account.msg_back')">
                <ArrowLeft :size="16" :stroke-width="2" aria-hidden="true" />
            </a>
            <div class="w-9 h-9 rounded-xl overflow-hidden shrink-0">
                <img v-if="conversation.other.avatar" :src="conversation.other.avatar" alt="" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center font-black text-white text-[13px] uppercase"
                    :style="{ backgroundColor: avatarBg(conversation.other.username) }" aria-hidden="true">{{ conversation.other.username[0] }}</div>
            </div>
            <div>
                <h2 class="text-[14px] font-bold" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ otherName }}</h2>
                <p class="text-[11px] font-mono" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">@{{ conversation.other.username }}</p>
            </div>
        </div>

        <!-- Messages -->
        <div class="flex-1 overflow-y-auto p-5 min-h-[300px] max-h-[500px]"
            role="log" aria-live="polite" :aria-label="t('account.msg_thread_label')">
            <div class="flex flex-col justify-end min-h-full gap-2">
                <div v-for="msg in [...messages.data].reverse()" :key="msg.id"
                    class="flex gap-2" :class="msg.is_mine ? 'justify-end' : 'justify-start'">
                    <div class="group relative max-w-[75%]">
                        <div class="px-4 py-2.5 rounded-2xl text-[13px] leading-relaxed"
                            :class="msg.is_mine
                                ? 'bg-blue-500 text-white rounded-br-sm'
                                : dark ? 'bg-zinc-800 text-zinc-200 rounded-bl-sm' : 'bg-zinc-100 text-zinc-800 rounded-bl-sm'">
                            <span v-if="msg.deleted" class="italic opacity-60">{{ t('account.msg_deleted') }}</span>
                            <span v-else>{{ msg.body }}</span>
                        </div>
                        <p class="text-[10px] mt-0.5 opacity-0 group-hover:opacity-100 transition" :class="[msg.is_mine ? 'text-right' : 'text-left', dark ? 'text-zinc-600' : 'text-zinc-400']">
                            {{ msg.at_human }}
                        </p>
                        <!-- Delete button for own messages -->
                        <!-- Also revealed on focus, so it is reachable by keyboard
                             rather than by hover only. -->
                        <button v-if="msg.is_mine && !msg.deleted" type="button"
                            class="absolute -top-1.5 -left-1.5 w-6 h-6 rounded-full items-center justify-center flex opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50"
                            :class="dark ? 'bg-zinc-800 text-red-400 hover:bg-red-500/20' : 'bg-white text-red-600 hover:bg-red-50 shadow-sm border border-zinc-300'"
                            :aria-label="t('account.msg_delete_one')" :title="t('account.msg_delete_one')"
                            @click="deleteMessage(msg.id)">
                            <Trash2 :size="11" :stroke-width="2" aria-hidden="true" />
                        </button>
                    </div>
                </div>
                <div ref="messagesEnd" />
            </div>
        </div>

        <!-- Typing indicator -->
        <p v-if="otherTyping" class="px-5 pb-1 text-[11.5px] italic" aria-live="polite"
            :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
            {{ t('account.msg_typing', { name: otherName }) }}
        </p>

        <!-- Send -->
        <div class="px-5 py-4 border-t" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <form class="flex gap-2" @submit.prevent="send">
                <label for="message-body" class="sr-only">{{ t('account.msg_write_label') }}</label>
                <textarea
                    id="message-body"
                    v-model="sendForm.body"
                    rows="1"
                    :placeholder="t('account.msg_write_placeholder')"
                    class="flex-1 rounded-xl border px-4 py-2.5 text-[13px] resize-none transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                    :class="dark
                        ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50'
                        : 'border-zinc-300 bg-white text-zinc-900 placeholder:text-zinc-500 focus:border-blue-500/60'"
                    :aria-invalid="sendForm.errors.body ? 'true' : undefined"
                    :aria-describedby="sendForm.errors.body ? 'message-error' : 'message-hint'"
                    @input="notifyTyping"
                    @keydown.enter.exact.prevent="send" />
                <button type="submit" :disabled="sendForm.processing || !sendForm.body.trim()"
                    class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white transition disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :aria-label="t('account.msg_send')" :title="t('account.msg_send')">
                    <Send :size="14" :stroke-width="2" aria-hidden="true" />
                </button>
            </form>
            <p v-if="sendForm.errors.body" id="message-error" role="alert"
               class="text-red-600 dark:text-red-400 text-[11.5px] font-semibold mt-1">{{ sendForm.errors.body }}</p>
            <p id="message-hint" class="text-[10.5px] mt-1.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                {{ t('account.msg_enter_hint') }}
            </p>
        </div>
    </div>
    </AccountPage>
</template>
