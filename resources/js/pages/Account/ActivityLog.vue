<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AccountPage from '@/components/Account/AccountPage.vue';
import ActivityLogList from '@/components/Account/ActivityLogList.vue';
import { useLocale } from '@/composables/useLocale';

interface LoginEntry {
    id: number; ip: string | null; user_agent: string | null;
    country: string | null; city: string | null;
    at: string; at_full: string;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator {
    data: LoginEntry[];
    links: PageLink[];
    meta: unknown;
    current_page?: number;
    last_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
}

defineProps<{
    history: Paginator;
    unreadNotifications?: number;
    unreadMessages?: number;
}>();

const { t } = useLocale();
</script>

<template>
    <Head :title="t('account.act_title')" />

    <AccountPage
        active-tab="activity"
        :section="t('account.act_title')"
        :unread-notifications="unreadNotifications"
        :unread-messages="unreadMessages"
    >
        <template #subtitle>{{ t('account.act_subtitle') }}</template>

        <ActivityLogList :history="history" />
    </AccountPage>
</template>
