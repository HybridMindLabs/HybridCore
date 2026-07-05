<script setup lang="ts">
import AdminSidebar from '@/components/Admin/Sidebar.vue';
import AdminTopbar from '@/components/Admin/Topbar.vue';
import CommandPalette from '@/components/Admin/CommandPalette.vue';
import ToastManager from '@/components/UI/ToastManager.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { router } from '@inertiajs/vue3';

withDefaults(defineProps<{ title?: string }>(), { title: 'Dashboard' });

useFlashToast();

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

            <main class="flex-1 px-4 sm:px-6 py-5 overflow-x-hidden">
                <slot />
            </main>
        </div>

        <ToastManager />
        <CommandPalette />
    </div>
</template>
