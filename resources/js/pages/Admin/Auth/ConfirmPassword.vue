<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';

const page = usePage<{ app: { name: string } }>();
const form = useForm({ password: '' });

function submit() {
    form.post(route('admin.password.confirm.store'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Confirm password" />
    <div class="hc-texture min-h-screen bg-admin-bg flex items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="flex items-center gap-3 justify-center mb-8">
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center">
                    <span class="text-blue-400 text-sm font-bold leading-none">HC</span>
                </div>
                <div class="leading-none">
                    <span class="text-zinc-100 text-base font-semibold tracking-tight block">{{ page.props.app.name }}</span>
                    <span class="text-zinc-500 text-xs mt-0.5 block">Admin Panel</span>
                </div>
            </div>

            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-6">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-amber-500/10 text-amber-400 mb-4">
                    <LockKeyhole :size="18" :stroke-width="2" />
                </div>
                <h1 class="text-zinc-100 text-lg font-semibold mb-1">Confirm your password</h1>
                <p class="text-zinc-400 text-sm mb-5">This download is protected. You won't be asked again for 15 minutes.</p>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-zinc-400 text-xs font-medium">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            autofocus
                            class="bg-zinc-900/60 border text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                            :class="form.errors.password ? 'border-red-500' : 'border-zinc-800/70'"
                        />
                        <p v-if="form.errors.password" class="text-red-400 text-xs">{{ form.errors.password }}</p>
                    </div>
                    <button type="submit" :disabled="form.processing || !form.password" class="px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-500 hover:bg-blue-400 transition-colors disabled:opacity-50">
                        Confirm
                    </button>
                    <Link :href="route('admin.dashboard')" class="text-center text-xs text-zinc-500 hover:text-zinc-300">Back to dashboard</Link>
                </form>
            </div>
        </div>
    </div>
</template>
