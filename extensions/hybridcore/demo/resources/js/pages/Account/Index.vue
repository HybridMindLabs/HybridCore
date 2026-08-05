<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AccountPage from '@/components/Account/AccountPage.vue';
import { Puzzle } from '@lucide/vue';

defineProps<{ message: string; unreadNotifications?: number; unreadMessages?: number }>();
</script>

<template>
    <Head title="Demo" />
    <AccountPage
        active-tab="demo"
        section="Demo"
        :unread-notifications="unreadNotifications"
        :unread-messages="unreadMessages"
    >
        <template #subtitle>accountTabs() registry example.</template>

        <div class="rounded-2xl border border-zinc-800/70 bg-[#111113] p-6 flex items-center gap-3">
            <Puzzle :size="20" :stroke-width="1.5" class="text-zinc-500 shrink-0" />
            <p class="text-zinc-400 text-sm">{{ message }}</p>
        </div>
    </AccountPage>
</template>
