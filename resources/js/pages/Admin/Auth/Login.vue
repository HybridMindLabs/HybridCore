<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LogIn } from '@lucide/vue';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';

interface SharedProps {
    flash: { error: string | null };
    app: { name: string };
}

const { t } = useLocale();
const page = usePage<SharedProps>();
const flashError = computed(() => page.props.flash?.error);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('admin.login.store'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Admin Login" />

    <div class="hc-texture min-h-screen bg-[#09090b] flex items-center justify-center px-4">
        <div class="w-full max-w-sm">

            <!-- Logo -->
            <div class="flex items-center gap-3 justify-center mb-8">
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center">
                    <span class="text-blue-400 text-sm font-bold leading-none">HC</span>
                </div>
                <div class="leading-none">
                    <span class="text-zinc-100 text-base font-semibold tracking-tight block">{{ page.props.app.name }}</span>
                    <span class="text-zinc-600 text-xs mt-0.5 block">Admin Panel</span>
                </div>
            </div>

            <!-- Card -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-6 shadow-[0_2px_8px_rgba(0,0,0,0.4)]">
                <h1 class="text-zinc-100 text-lg font-semibold mb-1">{{ t('auth.sign_in') }}</h1>
                <p class="text-zinc-400 text-sm mb-6">Access the admin panel</p>

                <!-- Flash error -->
                <div
                    v-if="flashError"
                    class="mb-4 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm"
                >
                    {{ flashError }}
                </div>

                <form @submit.prevent="submit" class="flex flex-col gap-4">

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">{{ t('auth.email') }}</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="admin@example.com"
                            class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm
                                   focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20
                                   placeholder:text-zinc-600"
                            :class="form.errors.email ? 'border-red-500' : ''"
                        />
                        <p v-if="form.errors.email" class="text-red-400 text-xs">{{ form.errors.email }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">{{ t('auth.password_label') }}</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm
                                   focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20
                                   placeholder:text-zinc-600"
                            :class="form.errors.password ? 'border-red-500' : ''"
                        />
                        <p v-if="form.errors.password" class="text-red-400 text-xs">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border border-zinc-800/70 bg-zinc-900/60 accent-blue-500"
                        />
                        <span class="text-zinc-400 text-sm">{{ t('auth.remember_me') }}</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-1 flex items-center justify-center gap-2 bg-blue-500 text-[#0a0f1a] font-semibold rounded-lg px-4 py-2.5 text-sm
                               hover:bg-blue-600 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <LogIn :size="15" :stroke-width="2" />
                        {{ t('auth.sign_in') }}
                    </button>
                </form>
            </div>

        </div>
    </div>
</template>
