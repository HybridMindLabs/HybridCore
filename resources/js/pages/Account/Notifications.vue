<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AccountPage from '@/components/Account/AccountPage.vue';
import NotificationsList from '@/components/Account/NotificationsList.vue';
import { useLocale } from '@/composables/useLocale';

interface NotifData {
    type: string;
    level?: string;
    message?: string;
    sender_username?: string;
    preview?: string;
    conversation_id?: number;
    action_url?: string;
    action_label?: string;
}
interface Notif {
    id: string;
    type: string;
    data: NotifData;
    read: boolean;
    created_at: string;
}

defineProps<{
    notifications: { data: Notif[]; links: any; meta: any };
    unreadNotifications?: number;
    unreadMessages?: number;
}>();

const { t } = useLocale();
</script>

<template>
    <Head :title="t('account.notif_title')" />

    <AccountPage
        active-tab="notifications"
        :section="t('account.notif_title')"
        :unread-notifications="unreadNotifications"
        :unread-messages="unreadMessages"
    >
        <template #subtitle>{{ t('account.notif_subtitle') }}</template>

        <NotificationsList :notifications="notifications" />
    </AccountPage>
</template>
