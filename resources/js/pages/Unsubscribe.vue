<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { MailX } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
    category: string;
    username: string;
}>();

const done = ref(false);

function confirm() {
    router.delete(route('unsubscribe.destroy', props.category), {
        onSuccess: () => { done.value = true; },
    });
}
</script>

<template>
    <Head title="Unsubscribe" />
    <PublicLayout>
        <div class="min-h-[60vh] flex items-center justify-center px-4">
            <div class="max-w-md w-full text-center">
                <div class="w-14 h-14 rounded-full bg-cyan-500/10 flex items-center justify-center mx-auto mb-5">
                    <MailX :size="24" :stroke-width="1.5" class="text-cyan-400" />
                </div>

                <template v-if="!done">
                    <h1 class="text-2xl font-black text-zinc-100 mb-2">Unsubscribe</h1>
                    <p class="text-zinc-400 mb-1">Hi <strong class="text-zinc-200">{{ username }}</strong>,</p>
                    <p class="text-zinc-500 mb-6">
                        You are about to unsubscribe from
                        <span class="text-zinc-200 font-semibold">{{ category }}</span> emails.
                        You can re-enable this anytime from your account settings.
                    </p>
                    <button type="button" @click="confirm"
                        class="bg-red-500/20 border border-red-500/30 text-red-300 font-semibold px-6 py-2.5 rounded-xl hover:bg-red-500/30 transition-colors">
                        Unsubscribe from {{ category }} emails
                    </button>
                </template>

                <template v-else>
                    <h1 class="text-2xl font-black text-zinc-100 mb-2">Done!</h1>
                    <p class="text-zinc-400 mb-6">You've been unsubscribed from {{ category }} emails.</p>
                    <a :href="route('home')" class="text-cyan-400 hover:underline text-sm">← Back to home</a>
                </template>
            </div>
        </div>
    </PublicLayout>
</template>
