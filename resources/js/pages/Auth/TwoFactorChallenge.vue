<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck, KeyRound } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { useTheme } from '@/composables/useTheme';

const { theme, init: initTheme } = useTheme();
const dark = computed(() => theme.value === 'dark');
onMounted(() => initTheme());

const useRecovery = ref(false);
const form = useForm({ code: '' });

function submit() {
    form.post(route('auth.2fa.verify'), {
        onFinish: () => { if (useRecovery.value === false) form.reset('code'); },
    });
}
</script>

<template>
    <Head title="Two-Factor Authentication" />

    <div class="relative min-h-screen flex items-center justify-center px-4 overflow-hidden"
        :class="dark ? 'bg-[#09090b]' : 'bg-zinc-100'">
        <!-- Glows + dot grid (same as Home hero) -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]"
                :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
            <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]"
                :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
            <div v-if="dark" class="absolute inset-0 opacity-50"
                style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
        </div>

        <div class="relative z-10 w-full max-w-sm">
            <div class="flex justify-center mb-8">
                <div class="w-12 h-12 rounded-2xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                    <component :is="useRecovery ? KeyRound : ShieldCheck" :size="22" :stroke-width="1.6" class="text-blue-400" />
                </div>
            </div>

            <div class="rounded-2xl border p-8"
                :class="dark ? 'bg-[#111113] border-zinc-800/70' : 'bg-white border-zinc-200 shadow-sm'">
                <div class="mx-auto mb-5 h-1 w-12 rounded-full bg-gradient-to-r from-blue-500 to-violet-500" />

                <h1 class="text-[18px] font-black text-center mb-1" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    Two-Factor Authentication
                </h1>
                <p class="text-[13px] text-center mb-6" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                    <template v-if="!useRecovery">Enter the 6-digit code from your authenticator app.</template>
                    <template v-else>Enter one of your recovery codes.</template>
                </p>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div>
                        <input
                            v-model="form.code"
                            type="text"
                            :inputmode="useRecovery ? 'text' : 'numeric'"
                            :maxlength="useRecovery ? 21 : 6"
                            :placeholder="useRecovery ? 'xxxxxxxxxx-xxxxxxxxxx' : '000000'"
                            autofocus
                            autocomplete="one-time-code"
                            class="w-full rounded-xl border px-4 py-3 text-[15px] font-mono text-center tracking-[0.25em] placeholder:tracking-normal focus:outline-none focus:ring-2 focus:ring-blue-500/10 transition"
                            :class="dark
                                ? 'border-zinc-800 bg-zinc-900/60 text-zinc-100 placeholder:text-zinc-700 focus:border-blue-500/50'
                                : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-300 focus:border-blue-400'"
                            @keyup.enter="submit"
                        />
                        <p v-if="form.errors.code" class="text-red-400 text-[12px] mt-1.5 text-center">{{ form.errors.code }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || form.code.length === 0"
                        class="w-full py-2.5 bg-blue-500 hover:bg-blue-600 text-white font-bold text-[14px] rounded-xl transition disabled:opacity-50 shadow-lg shadow-blue-500/20"
                    >
                        {{ form.processing ? 'Verifying…' : 'Continue' }}
                    </button>
                </form>

                <button
                    type="button"
                    class="mt-4 w-full text-center text-[12px] transition"
                    :class="dark ? 'text-zinc-600 hover:text-zinc-400' : 'text-zinc-400 hover:text-zinc-600'"
                    @click="useRecovery = !useRecovery; form.reset('code')"
                >
                    {{ useRecovery ? 'Use authenticator code instead' : 'Use a recovery code instead' }}
                </button>
            </div>
        </div>
    </div>
</template>
