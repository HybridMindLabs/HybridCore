<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import { ShieldCheck } from '@lucide/vue';

interface Previous { name: string; email: string }

const props = defineProps<{ previous: Previous }>();

const form = useForm({
    name:                  props.previous.name,
    email:                 props.previous.email,
    password:              '',
    password_confirmation: '',
});

function submit() { form.post(route('installer.admin.store')); }
</script>

<template>
    <Head title="Admin Account — Install" />

    <InstallerLayout :current-step="4">

        <div class="mb-6">
            <div class="flex items-center gap-2.5 mb-1">
                <ShieldCheck :size="17" :stroke-width="1.75" class="text-blue-400" />
                <h2 class="text-zinc-100 text-xl font-black tracking-tight">Admin Account</h2>
            </div>
            <p class="text-zinc-500 text-sm mt-1">Create the first administrator account.</p>
        </div>

        <form @submit.prevent="submit">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-2xl p-5 mb-5 flex flex-col gap-4">

                <div>
                    <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Full Name</label>
                    <input v-model="form.name" type="text" placeholder="Admin Name" autocomplete="name" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                    <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Email Address</label>
                    <input v-model="form.email" type="email" placeholder="admin@example.com" autocomplete="email" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                    <p v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Password</label>
                        <input v-model="form.password" type="password" placeholder="Min. 8 characters" autocomplete="new-password" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                        <p v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Confirm Password</label>
                        <input v-model="form.password_confirmation" type="password" placeholder="Repeat password" autocomplete="new-password" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                        <p v-if="form.errors.password_confirmation" class="text-red-400 text-xs mt-1">{{ form.errors.password_confirmation }}</p>
                    </div>
                </div>

            </div>

            <div class="flex items-center justify-between">
                <Link :href="route('installer.database')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-50">
                    {{ form.processing ? 'Saving…' : 'Continue →' }}
                </button>
            </div>
        </form>

    </InstallerLayout>
</template>