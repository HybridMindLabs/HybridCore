<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Mail, Eye, Trash2, Circle, CheckCircle2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface Message {
    id: number;
    name: string;
    email: string;
    subject: string | null;
    message: string;
    read_at: string | null;
    created_at: string;
}

interface Paginator {
    data: Message[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

defineProps<{ messages: Paginator; unreadCount: number }>();

function del(id: number) {
    if (!confirm('Delete this message? This cannot be undone.')) return;
    router.delete(route('admin.contact.destroy', id));
}

function preview(text: string, len = 90) {
    return text.length > len ? text.slice(0, len) + '…' : text;
}

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Contact Messages" />
    <AdminLayout title="Contact Messages">

        <PageHeader
            title="Contact Messages"
            :description="`${unreadCount} unread message${unreadCount !== 1 ? 's' : ''}`"
            :icon="Mail"
        />

        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <div v-if="messages.data.length === 0" class="p-10 text-center">
                <Mail :size="24" :stroke-width="1.5" class="mx-auto mb-3 text-zinc-700" />
                <p class="text-zinc-500 text-sm">No messages yet.</p>
            </div>

            <div v-else class="divide-y divide-zinc-800/50">
                <div
                    v-for="msg in messages.data"
                    :key="msg.id"
                    class="flex items-start gap-4 px-5 py-4 hover:bg-white/[0.02] transition-colors"
                    :class="!msg.read_at ? 'bg-blue-500/[0.03]' : ''"
                >
                    <!-- Read indicator -->
                    <div class="mt-1 shrink-0">
                        <CheckCircle2 v-if="msg.read_at" :size="16" :stroke-width="1.5" class="text-zinc-700" />
                        <Circle v-else :size="16" :stroke-width="2" class="text-blue-400" />
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-0.5">
                            <span class="text-sm font-medium" :class="msg.read_at ? 'text-zinc-400' : 'text-zinc-100'">
                                {{ msg.name }}
                            </span>
                            <span class="text-xs text-zinc-600 font-mono">{{ msg.email }}</span>
                            <span v-if="msg.subject" class="text-xs px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-500">{{ msg.subject }}</span>
                        </div>
                        <p class="text-xs text-zinc-600 truncate">{{ preview(msg.message) }}</p>
                        <p class="text-[11px] text-zinc-700 mt-1">{{ formatDate(msg.created_at) }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1 shrink-0">
                        <Link
                            :href="route('admin.contact.show', msg.id)"
                            class="p-2 rounded-lg text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors"
                            title="Read"
                        >
                            <Eye :size="14" :stroke-width="1.75" />
                        </Link>
                        <button
                            type="button"
                            class="p-2 rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-400/10 transition-colors"
                            title="Delete"
                            @click="del(msg.id)"
                        >
                            <Trash2 :size="14" :stroke-width="1.75" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="messages.last_page > 1"
                class="flex items-center justify-between px-5 py-3 border-t border-zinc-800/50"
            >
                <p class="text-xs text-zinc-600">
                    {{ messages.total }} messages — page {{ messages.current_page }} of {{ messages.last_page }}
                </p>
                <div class="flex gap-1">
                    <Link
                        v-if="messages.prev_page_url"
                        :href="messages.prev_page_url"
                        class="px-3 py-1.5 text-xs rounded-lg border border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                    >← Prev</Link>
                    <Link
                        v-if="messages.next_page_url"
                        :href="messages.next_page_url"
                        class="px-3 py-1.5 text-xs rounded-lg border border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                    >Next →</Link>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
