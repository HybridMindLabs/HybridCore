<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Puzzle, Bell, Settings, ExternalLink } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { useFlashToast } from '@/composables/useFlashToast';

defineProps<{ message: string; greeting: string; showOnboardingStep: boolean }>();

useFlashToast();

function sendTestNotification() {
    router.post(route('admin.demo.notify'), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Demo" />
    <AdminLayout title="Demo">
        <PageHeader title="Demo" :icon="Puzzle" description="Reference extension — every SDK registry demonstrated somewhere in this page or its links." />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="bg-[#111827] border border-[#1e2d47] rounded-xl p-5 text-[#94a3b8] text-sm">
                {{ message }}
                <p class="mt-2 text-zinc-500 text-xs">Greeting from settings: "{{ greeting }}"</p>
            </div>

            <div class="bg-[#111827] border border-[#1e2d47] rounded-xl p-5 flex flex-col gap-3">
                <button type="button"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors w-fit"
                    @click="sendTestNotification">
                    <Bell :size="14" :stroke-width="2" />
                    Send yourself a test notification
                </button>

                <Link :href="route('admin.settings.extensions.demo')"
                    class="inline-flex items-center gap-2 text-xs text-zinc-400 hover:text-zinc-100 w-fit">
                    <Settings :size="13" :stroke-width="2" />
                    Demo settings
                </Link>

                <a :href="route('demo.index')" target="_blank"
                    class="inline-flex items-center gap-2 text-xs text-zinc-400 hover:text-zinc-100 w-fit">
                    <ExternalLink :size="13" :stroke-width="2" />
                    Public page
                </a>

                <p v-if="showOnboardingStep" class="text-xs text-zinc-500">
                    Onboarding step is enabled — new users will see it.
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
