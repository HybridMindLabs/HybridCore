<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, LogOut, Menu, UserCircle } from '@lucide/vue';
import LanguageSwitcher from '@/components/UI/LanguageSwitcher.vue';
import GlobalSearch from '@/components/GlobalSearch.vue';
import { computed, ref } from 'vue';
import { useAdminSidebar } from '@/composables/useAdminSidebar';

defineProps<{ title: string }>();

interface SharedProps {
    auth: { user: { name: string; email: string } | null };
    flash: { success: string | null; error: string | null };
    adminBadges?: { unread_contact: number };
}

const page = usePage<SharedProps>();
const menuOpen = ref(false);
const unreadContact = computed(() => page.props.adminBadges?.unread_contact ?? 0);
const { toggle: toggleSidebar } = useAdminSidebar();

function logout() {
    router.post(route('admin.logout'));
}
</script>

<template>
    <header class="h-12 border-b border-zinc-800/70 bg-[#0d0d0f] flex items-center justify-between px-3 sm:px-5 shrink-0 sticky top-0 z-30">
        <div class="flex items-center gap-3 min-w-0">
            <button
                type="button"
                class="lg:hidden w-8 h-8 -ml-1 shrink-0 rounded-lg flex items-center justify-center text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900/60 transition-colors"
                @click="toggleSidebar"
            >
                <Menu :size="18" :stroke-width="1.75" />
            </button>
            <h1 class="text-zinc-500 text-xs font-medium tracking-wide uppercase truncate">{{ title }}</h1>
        </div>

        <!-- Right side -->
        <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
            <!-- Global Search -->
            <GlobalSearch />

            <LanguageSwitcher />

            <!-- Contact notifications -->
            <Link
                :href="route('admin.contact.index')"
                class="relative w-8 h-8 rounded-lg flex items-center justify-center text-zinc-600 hover:text-zinc-400 hover:bg-zinc-900/60 transition-colors"
                :title="unreadContact > 0 ? `${unreadContact} unread messages` : 'Contact messages'"
            >
                <Bell :size="15" :stroke-width="1.75" />
                <span
                    v-if="unreadContact > 0"
                    class="absolute -top-0.5 -right-0.5 min-w-[14px] h-[14px] px-0.5 rounded-full bg-red-500 text-white text-[9px] font-bold leading-[14px] text-center"
                >{{ unreadContact > 99 ? '99+' : unreadContact }}</span>
            </Link>

            <!-- User menu -->
            <div class="relative">
                <button
                    class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-zinc-900/60 transition-colors"
                    @click="menuOpen = !menuOpen"
                >
                    <div class="w-6 h-6 rounded-full bg-blue-500/10 border border-blue-500/30 flex items-center justify-center shrink-0">
                        <span class="text-blue-400 text-[10px] font-bold uppercase leading-none">
                            {{ page.props.auth?.user?.name?.charAt(0) ?? 'A' }}
                        </span>
                    </div>
                    <span class="text-zinc-400 text-xs hidden sm:block">
                        {{ page.props.auth?.user?.name ?? 'Admin' }}
                    </span>
                    <ChevronDown :size="12" :stroke-width="2" class="text-zinc-600 hidden sm:block" />
                </button>

                <div
                    v-if="menuOpen"
                    class="absolute right-0 top-full mt-1 w-44 bg-[#111113] border border-zinc-800/70 rounded-xl shadow-xl shadow-black/40 py-1 z-50"
                    @click="menuOpen = false"
                >
                    <Link
                        :href="route('admin.profile.edit')"
                        class="flex items-center gap-2.5 px-3 py-2 text-xs text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900/60 transition-colors"
                    >
                        <UserCircle :size="14" :stroke-width="1.75" />
                        Profile
                    </Link>
                    <div class="border-t border-zinc-800/70 my-1" />
                    <button
                        class="flex items-center gap-2.5 px-3 py-2 text-xs text-zinc-400 hover:text-red-400 hover:bg-red-500/8 transition-colors w-full text-left"
                        @click="logout"
                    >
                        <LogOut :size="14" :stroke-width="1.75" />
                        Sign out
                    </button>
                </div>

                <div v-if="menuOpen" class="fixed inset-0 z-40" @click="menuOpen = false" />
            </div>
        </div>
    </header>
</template>
