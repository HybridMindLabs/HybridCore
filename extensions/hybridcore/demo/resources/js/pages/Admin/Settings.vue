<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Settings } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { useFlashToast } from '@/composables/useFlashToast';

const props = defineProps<{ greeting: string; showOnboardingStep: boolean }>();

useFlashToast();

const form = useForm({
    greeting: props.greeting,
    show_onboarding_step: props.showOnboardingStep,
});

function submit() {
    form.patch(route('admin.settings.extensions.demo.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Demo Settings" />
    <AdminLayout title="Demo Settings">
        <PageHeader title="Demo Settings" :icon="Settings" description="settings() registry example — persisted through the core SettingsService." />

        <form class="bg-[#111827] border border-[#1e2d47] rounded-xl p-5 flex flex-col gap-4 max-w-md" @submit.prevent="submit">
            <div>
                <label class="block text-xs font-semibold text-zinc-400 mb-1.5">Greeting</label>
                <input v-model="form.greeting" type="text" class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-zinc-200 focus:outline-none focus:border-blue-500/50" />
                <p v-if="form.errors.greeting" class="text-red-400 text-xs mt-1">{{ form.errors.greeting }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-zinc-300">
                <input v-model="form.show_onboarding_step" type="checkbox" class="rounded border-zinc-700" />
                Show onboarding step to new users
            </label>

            <button type="submit" :disabled="form.processing" class="px-3 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-40 transition-colors w-fit">
                Save
            </button>
        </form>
    </AdminLayout>
</template>
