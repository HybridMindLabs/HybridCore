<script setup lang="ts">
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import AccountSidebar from '@/components/Account/AccountSidebar.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

withDefaults(defineProps<{
    activeTab: string;
    unreadNotifications?: number;
    unreadMessages?: number;
    /** False when used from the tabbed Account/Index.vue — tab clicks switch in place. */
    standalone?: boolean;
    /** Current section name shown in the breadcrumb (e.g. "Messages", "Favorites"). */
    section?: string;
}>(), { standalone: true });

const emit = defineEmits<{ 'update:activeTab': [value: string] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <PublicLayout>

        <!-- ── Compact hero (matches public pages) ── -->
        <div
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
        >
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[400px] rounded-full blur-[130px]"
                    :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
                <div class="absolute -top-20 right-1/4 w-[300px] h-[300px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 max-w-5xl mx-auto px-3 sm:px-6 py-8">
                <Breadcrumb :items="[
                    { label: 'Home', href: route('home') },
                    section
                        ? { label: t('account.my_account'), href: route('account.index') }
                        : { label: t('account.my_account') },
                    ...(section ? [{ label: section }] : []),
                ]" />

                <h1 class="text-[26px] sm:text-[30px] font-black tracking-tight leading-tight"
                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ section ?? t('account.my_account') }}
                </h1>
                <p class="text-[13px] mt-1" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                    <slot name="subtitle">{{ t('account.account_subtitle') ?? 'Manage your profile, security and preferences.' }}</slot>
                </p>
            </div>
        </div>

        <div class="px-3 sm:px-6 py-8">
            <div class="max-w-5xl mx-auto">
                <div class="flex gap-6 items-start">
                    <div class="hidden sm:block">
                        <AccountSidebar
                            :active-tab="activeTab"
                            :unread-notifications="unreadNotifications"
                            :unread-messages="unreadMessages"
                            :standalone="standalone"
                            @update:active-tab="emit('update:activeTab', $event)"
                        />
                    </div>
                    <div class="flex-1 min-w-0 flex flex-col gap-4">
                        <slot />
                        <ExtensionSlot name="account.panel.bottom" :context="{ activeTab }" />
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
