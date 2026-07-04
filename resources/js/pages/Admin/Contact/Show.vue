<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Mail, ArrowLeft, Trash2, Send, CheckCircle2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface ContactMessage {
    id: number;
    name: string;
    email: string;
    subject: string | null;
    message: string;
    ip: string | null;
    read_at: string | null;
    reply_body: string | null;
    replied_at: string | null;
    created_at: string;
}

const props = defineProps<{ message: ContactMessage }>();

const replyForm = useForm({ body: '' });

function sendReply() {
    replyForm.post(route('admin.contact.reply', props.message.id), {
        onSuccess: () => { replyForm.reset(); },
    });
}

function del() {
    if (!confirm('Delete this message?')) return;
    router.delete(route('admin.contact.destroy', props.message.id));
}

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-GB', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

const inputClass = 'w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 resize-none';
</script>

<template>
    <Head :title="`Message from ${message.name}`" />
    <AdminLayout :title="`Message from ${message.name}`">

        <!-- Top bar -->
        <div class="flex items-center justify-between mb-6">
            <Link
                :href="route('admin.contact.index')"
                class="flex items-center gap-1.5 text-sm text-zinc-600 hover:text-blue-400 transition-colors"
            >
                <ArrowLeft :size="13" :stroke-width="1.75" />
                Inbox
            </Link>
            <button
                type="button"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs border border-red-500/30 text-red-400 hover:bg-red-400/10 transition-colors"
                @click="del"
            >
                <Trash2 :size="12" :stroke-width="1.75" />
                Delete
            </button>
        </div>

        <div class="max-w-2xl flex flex-col gap-5">

            <!-- Header card -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center shrink-0">
                        <Mail :size="18" class="text-blue-400" :stroke-width="1.5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-semibold text-zinc-100">{{ message.name }}</h2>
                        <a :href="`mailto:${message.email}`" class="text-sm text-blue-400 hover:underline">{{ message.email }}</a>
                        <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-zinc-600">
                            <span>{{ formatDate(message.created_at) }}</span>
                            <span v-if="message.ip" class="font-mono">IP: {{ message.ip }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="message.subject" class="mt-4 pt-4 border-t border-zinc-800/50">
                    <p class="text-xs text-zinc-600 uppercase tracking-wider font-medium mb-1">Subject</p>
                    <p class="text-sm text-zinc-200 font-medium">{{ message.subject }}</p>
                </div>
            </div>

            <!-- Message body -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <p class="text-xs text-zinc-600 uppercase tracking-wider font-medium mb-3">Message</p>
                <div class="text-sm text-zinc-300 leading-relaxed whitespace-pre-wrap">{{ message.message }}</div>
            </div>

            <!-- Previous reply (if any) -->
            <div v-if="message.replied_at" class="bg-[#111113] border border-emerald-500/20 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <CheckCircle2 :size="14" :stroke-width="1.75" class="text-emerald-400" />
                    <p class="text-xs text-emerald-400 font-medium uppercase tracking-wider">
                        Replied {{ formatDate(message.replied_at) }}
                    </p>
                </div>
                <div class="text-sm text-zinc-300 leading-relaxed whitespace-pre-wrap">{{ message.reply_body }}</div>
            </div>

            <!-- Reply form -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <p class="text-xs text-zinc-600 uppercase tracking-wider font-medium mb-3">
                    {{ message.replied_at ? 'Send another reply' : 'Reply' }} to {{ message.email }}
                </p>
                <form @submit.prevent="sendReply" class="flex flex-col gap-3">
                    <textarea
                        v-model="replyForm.body"
                        rows="5"
                        :class="inputClass"
                        placeholder="Type your reply..."
                        :disabled="replyForm.processing"
                    />
                    <p v-if="replyForm.errors.body" class="text-xs text-red-400">{{ replyForm.errors.body }}</p>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-500 transition-colors disabled:opacity-50"
                            :disabled="replyForm.processing || !replyForm.body.trim()"
                        >
                            <Send :size="13" :stroke-width="1.75" />
                            Send Reply
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </AdminLayout>
</template>
