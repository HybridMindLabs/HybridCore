<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, ExternalLink, LogOut, Menu, Search, UserCircle } from '@lucide/vue';
import NotificationsMenu from '@/components/Admin/NotificationsMenu.vue';
import { ref } from 'vue';
import { useAdminSidebar } from '@/composables/useAdminSidebar';
import { useAdminSearch } from '@/composables/useAdminSearch';

defineProps<{ title: string }>();

interface SharedProps {
    auth: { user: { name: string; email: string } | null };
}

const page = usePage<SharedProps>();
const menuOpen = ref(false);
const { mobileOpen, toggle: toggleSidebar } = useAdminSidebar();
const { show: openSearch } = useAdminSearch();

function logout() {
    router.post(route('admin.logout'));
}
</script>

<template>
    <header class="h-14 border-b border-zinc-800/70 bg-admin-sunken flex items-center justify-between gap-3 px-3 sm:px-6 shrink-0 sticky top-0 z-30">
        <div class="flex items-center gap-3 min-w-0">
            <button
                type="button"
                class="-ml-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-zinc-400 transition-colors hover:bg-zinc-900/60 hover:text-zinc-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/70 lg:hidden"
                :aria-expanded="mobileOpen"
                aria-controls="admin-navigation"
                :aria-label="mobileOpen ? 'Close administration navigation' : 'Open administration navigation'"
                @click.stop="toggleSidebar"
            >
                <Menu :size="20" :stroke-width="1.8" aria-hidden="true" />
            </button>
            <h1 class="text-zinc-100 text-[15px] font-semibold truncate">{{ title }}</h1>
        </div>

        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
            <button
                type="button"
                class="flex items-center gap-2 h-9 px-2.5 sm:pl-3 sm:pr-2 rounded-lg border border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700 transition-colors"
                aria-label="Search the admin panel"
                @click="openSearch"
            >
                <Search :size="15" :stroke-width="1.75" class="shrink-0" />
                <span class="hidden md:block text-sm pr-6">Search…</span>
                <kbd class="hidden md:block text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-900 border border-zinc-800 text-zinc-500">Ctrl K</kbd>
            </button>

            <a
                :href="route('home')"
                target="_blank"
                rel="noopener"
                class="flex items-center gap-1.5 h-9 px-2.5 rounded-lg text-zinc-500 hover:text-zinc-200 hover:bg-zinc-900/60 transition-colors"
                title="Open the public site in a new tab"
            >
                <ExternalLink :size="15" :stroke-width="1.75" />
                <span class="hidden lg:block text-sm">View site</span>
            </a>

            <NotificationsMenu />

            <div class="relative">
                <button
                    type="button"
                    class="flex items-center gap-2 h-9 pl-1.5 pr-2 rounded-lg hover:bg-zinc-900/60 transition-colors"
                    :aria-expanded="menuOpen"
                    @click="menuOpen = !menuOpen"
                >
                    <div class="w-7 h-7 rounded-full bg-blue-500/10 border border-blue-500/30 flex items-center justify-center shrink-0">
                        <span class="text-blue-400 text-[11px] font-bold uppercase leading-none">
                            {{ page.props.auth?.user?.name?.charAt(0) ?? 'A' }}
                        </span>
                    </div>
                    <span class="text-zinc-300 text-sm hidden sm:block max-w-32 truncate">
                        {{ page.props.auth?.user?.name ?? 'Admin' }}
                    </span>
                    <ChevronDown :size="13" :stroke-width="2" class="text-zinc-500 hidden sm:block" />
                </button>

                <template v-if="menuOpen">
                    <div class="fixed inset-0 z-40" @click="menuOpen = false" />
                    <div
                        class="absolute right-0 top-full mt-1.5 w-56 bg-admin-surface border border-zinc-800/70 rounded-xl shadow-xl shadow-black/40 py-1 z-50"
                        @click="menuOpen = false"
                    >
                        <div class="px-3 py-2 border-b border-zinc-800/70 mb-1">
                            <p class="text-sm text-zinc-100 truncate">{{ page.props.auth?.user?.name }}</p>
                            <p class="text-xs text-zinc-500 truncate">{{ page.props.auth?.user?.email }}</p>
                        </div>
                        <Link
                            :href="route('admin.profile.edit')"
                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900/60 transition-colors"
                        >
                            <UserCircle :size="15" :stroke-width="1.75" />
                            Profile
                        </Link>
                        <div class="border-t border-zinc-800/70 my-1" />
                        <button
                            type="button"
                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-zinc-400 hover:text-red-400 hover:bg-red-500/8 transition-colors w-full text-left"
                            @click="logout"
                        >
                            <LogOut :size="15" :stroke-width="1.75" />
                            Sign out
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </header>
</template>
