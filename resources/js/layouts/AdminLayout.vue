<script setup lang="ts">
import AdminSidebar from '@/components/Admin/Sidebar.vue';
import AdminTopbar from '@/components/Admin/Topbar.vue';
import CommandPalette from '@/components/Admin/CommandPalette.vue';
import ToastManager from '@/components/UI/ToastManager.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { ShieldAlert } from '@lucide/vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

withDefaults(defineProps<{ title?: string }>(), { title: 'Dashboard' });

useFlashToast();

const page = usePage<{ twoFactorGraceDaysRemaining: number | null }>();
const twoFactorGraceDaysRemaining = computed(() => page.props.twoFactorGraceDaysRemaining);

useKeyboardShortcuts({
    'g+d': () => router.visit(route('admin.dashboard')),
    'g+u': () => router.visit(route('admin.users.index')),
    'g+s': () => router.visit(route('admin.settings.index')),
});
</script>

<template>
    <div class="min-h-screen bg-[#09090b] flex overflow-x-hidden">
        <AdminSidebar />

        <div class="flex flex-col flex-1 min-w-0 lg:ml-[220px]">
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
        <CommandPalette />
    </div>
</template>
