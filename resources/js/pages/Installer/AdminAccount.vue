<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import StepHeader from '@/components/Installer/StepHeader.vue';
import Field from '@/components/Installer/Field.vue';
import { ShieldCheck, Info, ArrowRight, Loader2 } from '@lucide/vue';

interface Previous { name: string; email: string }

const props = defineProps<{ previous: Previous }>();

const form = useForm({
    name: props.previous.name,
    email: props.previous.email,
    password: '',
    password_confirmation: '',
});

function submit() { form.post(route('installer.admin.store')); }
</script>

<template>
    <Head title="Admin account — Install" />

    <InstallerLayout :current-step="4">

        <StepHeader
            :icon="ShieldCheck"
            title="Create your account"
            description="This is the first account on your community, and it gets full administrator access — settings, users, servers and extensions. You will be signed in with it once setup finishes."
        />

        <form @submit.prevent="submit">
            <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-2xl p-5 mb-5 flex flex-col gap-4">

                <Field
                    v-model="form.name"
                    label="Your name"
                    placeholder="Alex Petrov"
                    autocomplete="name"
                    :error="form.errors.name"
                    hint="Shown on your public profile. You can change it later."
                />

                <Field
                    v-model="form.email"
                    label="Email address"
                    type="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    :error="form.errors.email"
                    hint="Used to sign in and to reset your password — make sure you can receive mail here."
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field
                        v-model="form.password"
                        label="Password"
                        type="password"
                        placeholder="At least 8 characters"
                        autocomplete="new-password"
                        :error="form.errors.password"
                        hint="Use something long and unique."
                    />
                    <Field
                        v-model="form.password_confirmation"
                        label="Confirm password"
                        type="password"
                        placeholder="Repeat it"
                        autocomplete="new-password"
                        :error="form.errors.password_confirmation"
                    />
                </div>

            </div>

            <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl p-4 mb-6 flex items-start gap-3">
                <Info :size="14" :stroke-width="1.75" class="text-blue-400/70 shrink-0 mt-0.5" />
                <p class="text-zinc-500 text-xs leading-relaxed">
                    A username is generated from your name and shown publicly instead of your email address,
                    which stays private. You can link Steam or Discord to this account later from your profile.
                </p>
            </div>

            <div class="flex items-center justify-between gap-4">
                <Link :href="route('installer.database')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-50"
                >
                    <Loader2 v-if="form.processing" :size="14" :stroke-width="2.25" class="animate-spin" />
                    {{ form.processing ? 'Saving…' : 'Continue' }}
                    <ArrowRight v-if="!form.processing" :size="14" :stroke-width="2.25" />
                </button>
            </div>
        </form>

    </InstallerLayout>
</template>
