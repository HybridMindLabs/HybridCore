<script setup lang="ts">
import AdminSidebar from '@/components/Admin/Sidebar.vue';
import AdminTopbar from '@/components/Admin/Topbar.vue';
import AdminSearch from '@/components/Admin/AdminSearch.vue';
import ConfirmDialog from '@/components/Admin/ConfirmDialog.vue';
import ShortcutsHelp from '@/components/Admin/ShortcutsHelp.vue';
import SudoDialog from '@/components/Admin/SudoDialog.vue';
import { requireSudo } from '@/composables/useSudo';
import { useAdminSearch } from '@/composables/useAdminSearch';
import ToastManager from '@/components/UI/ToastManager.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { ShieldAlert } from '@lucide/vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import type { PendingVisit } from '@inertiajs/core';

withDefaults(defineProps<{ title?: string }>(), { title: 'Dashboard' });

useFlashToast();

const page = usePage<{ twoFactorGraceDaysRemaining: number | null }>();
const twoFactorGraceDaysRemaining = computed(() => page.props.twoFactorGraceDaysRemaining);

const { open: searchOpen, show: showSearch, hide: hideSearch } = useAdminSearch();
const shortcutsOpen = ref(false);

// Sudo mode: a protected action answers 423 — ask for the password, then
// replay the exact request that was refused.
let lastVisit: PendingVisit | null = null;
const stopListeners: (() => void)[] = [];

onMounted(() => {
    stopListeners.push(
        // Only writes are sudo-protected; ignoring GETs keeps background
        // prefetches and polls from overwriting the visit to replay.
        router.on('before', (event) => {
            if (event.detail.visit.method !== 'get') lastVisit = event.detail.visit;
        }),
        router.on('httpException', (event) => {
            if (event.detail.response.status !== 423 || !lastVisit) return;
            event.preventDefault();
            const visit = lastVisit;
            requireSudo().then((confirmed) => {
                if (!confirmed) return;
                router.visit(visit.url, {
                    method: visit.method,
                    data: visit.data,
                    preserveScroll: visit.preserveScroll,
                    preserveState: visit.preserveState,
                    forceFormData: visit.forceFormData,
                });
            });
        }),
    );
});
onUnmounted(() => stopListeners.forEach((stop) => stop()));

useKeyboardShortcuts({
    'ctrl+k': showSearch,
    'meta+k': showSearch,
    '?': () => { shortcutsOpen.value = true; },
    'Escape': () => {
        if (searchOpen.value) hideSearch();
        shortcutsOpen.value = false;
    },
    'g d': () => router.visit(route('admin.dashboard')),
    'g u': () => router.visit(route('admin.users.index')),
    'g v': () => router.visit(route('admin.servers.index')),
    'g p': () => router.visit(route('admin.payments.index')),
    'g s': () => router.visit(route('admin.settings.index')),
});
</script>

<template>
    <div class="admin-shell min-h-screen bg-admin-bg flex overflow-x-hidden">
        <AdminSidebar />

        <div class="flex flex-col flex-1 min-w-0 lg:ml-64">
            <AdminTopbar :title="title" />

            <div
                v-if="twoFactorGraceDaysRemaining !== null"
                class="flex items-center gap-2 px-4 sm:px-6 py-2 bg-amber-500/10 border-b border-amber-500/20 text-amber-300 text-xs"
            >
                <ShieldAlert :size="14" :stroke-width="2" class="shrink-0" />
                <span>
                    {{ twoFactorGraceDaysRemaining > 0
                        ? `Two-factor authentication is required for admin access — ${twoFactorGraceDaysRemaining} day(s) left to set it up.`
                        : 'Two-factor authentication is required for admin access.' }}
                </span>
                <Link :href="route('account.index', { tab: 'security' })" class="underline hover:no-underline shrink-0 ml-auto">Set up now</Link>
            </div>

            <main class="flex-1 px-4 sm:px-6 py-5 overflow-x-hidden">
                <slot />
            </main>
        </div>

        <ToastManager />
        <AdminSearch />
        <ShortcutsHelp :open="shortcutsOpen" @close="shortcutsOpen = false" />
        <ConfirmDialog />
        <SudoDialog />
    </div>
</template>
