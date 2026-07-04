<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MessageSquare, Plus, Search } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import AccountPage from '@/components/Account/AccountPage.vue';
import { computed, ref } from 'vue';

interface Other { id: number; username: string; display_name: string; avatar: string | null }
interface LastMsg { body: string; is_mine: boolean; at: string }
interface Conv { id: number; other: Other; last_message: LastMsg | null; unread: number }

defineProps<{ conversations: Conv[]; unreadNotifications?: number; unreadMessages?: number }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const showNewDm = ref(false);
const newDmForm = useForm({ username: '' });

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}
function startConversation() {
    newDmForm.post(route('account.messages.start'), { onSuccess: () => { showNewDm.value = false; } });
}
</script>

<template>
    <Head title="Messages" />

    <AccountPage active-tab="messages" section="Messages" :unread-notifications="unreadNotifications" :unread-messages="unreadMessages">
        <template #subtitle>Private conversations with other members.</template>
    <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
        <div class="px-6 py-4 border-b flex items-center justify-between" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <div>
                <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Messages</p>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Private conversations with other members.</p>
            </div>
            <button type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-bold transition"
                :class="dark ? 'border-blue-500/30 text-blue-400 hover:bg-blue-500/10' : 'border-blue-200 text-blue-600 hover:bg-blue-50'"
                @click="showNewDm = !showNewDm">
                <Plus :size="12" :stroke-width="2.5" /> New message
            </button>
        </div>

        <!-- New DM form -->
        <div v-if="showNewDm" class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-zinc-900/40' : 'border-zinc-100 bg-zinc-50'">
            <form class="flex gap-2" @submit.prevent="startConversation">
                <div class="relative flex-1">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-[13px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">@</span>
                    <input v-model="newDmForm.username" type="text" placeholder="username" autofocus
                        class="w-full pl-7 pr-4 py-2 rounded-xl border text-[13px] font-mono transition"
                        :class="dark ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50' : 'border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:border-blue-400'" />
                </div>
                <button type="submit" :disabled="newDmForm.processing"
                    class="px-4 py-2 rounded-xl bg-blue-500 hover:bg-blue-600 text-white text-[13px] font-bold transition disabled:opacity-60">
                    Start
                </button>
            </form>
            <p v-if="newDmForm.errors.username" class="text-red-400 text-[12px] font-semibold mt-1">{{ newDmForm.errors.username }}</p>
        </div>

        <!-- Empty state -->
        <div v-if="conversations.length === 0" class="p-12 text-center">
            <MessageSquare :size="28" :stroke-width="1.5" class="mx-auto mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
            <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No conversations yet</p>
            <p class="text-[12px] mt-1" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Send a message to start talking with other members.</p>
        </div>

        <!-- Conversation list -->
        <div v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-100'">
            <Link v-for="conv in conversations" :key="conv.id" :href="route('account.messages.show', conv.id)"
                class="flex items-center gap-3 px-5 py-4 transition"
                :class="dark ? 'hover:bg-zinc-900/40' : 'hover:bg-zinc-50'">
                <!-- Avatar -->
                <div class="w-10 h-10 rounded-xl overflow-hidden shrink-0">
                    <img v-if="conv.other.avatar" :src="conv.other.avatar" class="w-full h-full object-cover" :alt="conv.other.username" />
                    <div v-else class="w-full h-full flex items-center justify-center font-black text-white text-[14px] uppercase"
                        :style="{ backgroundColor: avatarBg(conv.other.username) }">{{ conv.other.username[0] }}</div>
                </div>
                <!-- Text -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <p class="text-[13px] font-bold truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ conv.other.display_name || conv.other.username }}
                        </p>
                        <span class="text-[11px] shrink-0 ml-2" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ conv.last_message?.at }}</span>
                    </div>
                    <p v-if="conv.last_message" class="text-[12px] truncate mt-0.5"
                        :class="conv.unread > 0 ? (dark ? 'text-zinc-300 font-semibold' : 'text-zinc-700 font-semibold') : (dark ? 'text-zinc-600' : 'text-zinc-400')">
                        <span v-if="conv.last_message.is_mine" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">You: </span>
                        {{ conv.last_message.body }}
                    </p>
                </div>
                <!-- Unread badge -->
                <span v-if="conv.unread > 0" class="shrink-0 w-5 h-5 rounded-full bg-blue-500 text-white text-[10px] font-bold flex items-center justify-center">
                    {{ conv.unread > 9 ? '9+' : conv.unread }}
                </span>
            </Link>
        </div>
    </div>
    </AccountPage>
</template>
