<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Menu, Moon, Sun, User, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import SocialLinks from '@/components/UI/SocialLinks.vue';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { safeUrl } from '@/lib/safeUrl';
import HeaderLanguageMenu from './HeaderLanguageMenu.vue';
import HeaderNotifications from './HeaderNotifications.vue';
import HeaderUserMenu from './HeaderUserMenu.vue';
import { publicNavIcon, usePublicNav, type SharedProps } from './usePublicNav';

const { t } = useLocale();
const { theme, toggle: toggleTheme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const page = usePage<SharedProps>();
const user = computed(() => page.props.auth?.user ?? null);

const { navLinks, isActive, publicNav, headerMenu } = usePublicNav();

const mobileOpen = ref(false);

function logout() {
    mobileOpen.value = false;
    router.post(route('logout'));
}

const linkClass = (active: boolean) => active
    ? (dark.value ? 'text-white bg-zinc-800/70' : 'text-zinc-900 bg-zinc-100')
    : (dark.value ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/40' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100/70');

const mobileLink = computed(() => dark.value
    ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/60'
    : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100');

const iconButton = computed(() => dark.value
    ? 'border-zinc-800 text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60'
    : 'border-zinc-200 text-zinc-400 hover:text-zinc-500 hover:bg-zinc-100');
</script>

<template>
    <header class="sticky top-0 z-50 border-b backdrop-blur-xl transition-colors duration-200"
        :class="dark ? 'border-zinc-800/60 bg-[#09090b]/80' : 'border-zinc-200/70 bg-white/80'">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 h-16 flex items-center gap-6">

            <!-- Brand — a plain wordmark until the owner uploads a real logo. -->
            <Link :href="route('home')"
                class="group flex items-center shrink-0 rounded-lg px-1 -mx-1 outline-none transition focus-visible:ring-2 focus-visible:ring-hc-accent/50">
                <span class="text-[17px] font-black tracking-tight transition-colors duration-200"
                    :class="dark ? 'text-white group-hover:text-hc-accent-hover' : 'text-zinc-900 group-hover:text-hc-accent'">
                    {{ page.props.app.name }}
                </span>
            </Link>

            <!-- Desktop nav -->
            <nav class="hidden md:flex items-center gap-0.5 flex-1" :aria-label="t('navigation.nav_home')">
                <a v-for="link in navLinks" :key="link.key" :href="link.href"
                    :aria-current="isActive(link.href) ? 'page' : undefined"
                    class="group relative flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium rounded-lg outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-hc-accent/50"
                    :class="linkClass(isActive(link.href))">
                    <component :is="link.icon" :size="14" :stroke-width="1.75" class="transition-transform duration-200 group-hover:-translate-y-px" aria-hidden="true" />
                    {{ t('navigation.' + link.key) }}
                    <span v-if="isActive(link.href)" class="absolute inset-x-3 -bottom-px h-px rounded-full bg-hc-accent" aria-hidden="true" />
                </a>

                <!-- Extension-registered public links -->
                <a v-for="item in publicNav" :key="item.url" :href="safeUrl(item.url)"
                    :aria-current="isActive(item.url) ? 'page' : undefined"
                    class="group relative flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium rounded-lg outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-hc-accent/50"
                    :class="linkClass(isActive(item.url))">
                    <component :is="publicNavIcon(item.icon)" :size="14" :stroke-width="1.75" class="transition-transform duration-200 group-hover:-translate-y-px" aria-hidden="true" />
                    {{ item.label }}
                    <span v-if="isActive(item.url)" class="absolute inset-x-3 -bottom-px h-px rounded-full bg-hc-accent" aria-hidden="true" />
                </a>

                <!-- Admin-managed header menu. focus-within keeps a submenu reachable by keyboard. -->
                <template v-for="item in headerMenu" :key="item.label">
                    <div v-if="item.children?.length" class="relative group">
                        <button type="button" aria-haspopup="true"
                            class="flex items-center gap-1 px-3 py-2 text-[13px] font-medium rounded-lg outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-hc-accent/50"
                            :class="linkClass(false)">
                            {{ item.label }}
                            <ChevronDown :size="11" :stroke-width="2" class="transition-transform duration-200 group-hover:rotate-180 group-focus-within:rotate-180" aria-hidden="true" />
                        </button>
                        <div class="absolute top-full left-0 mt-1.5 min-w-[180px] rounded-xl border shadow-xl py-1.5 z-50
                                    opacity-0 invisible -translate-y-1
                                    group-hover:opacity-100 group-hover:visible group-hover:translate-y-0
                                    group-focus-within:opacity-100 group-focus-within:visible group-focus-within:translate-y-0
                                    transition-all duration-200"
                            :class="dark ? 'bg-zinc-900/95 backdrop-blur-xl border-zinc-800 shadow-black/50' : 'bg-white/95 backdrop-blur-xl border-zinc-200 shadow-zinc-300/40'">
                            <a v-for="child in item.children" :key="child.label" :href="safeUrl(child.url)" :target="child.target"
                                :rel="child.target === '_blank' ? 'noopener noreferrer' : undefined"
                                class="block px-3.5 py-2 text-[13px] outline-none transition-colors focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-hc-accent/50"
                                :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'">{{ child.label }}</a>
                        </div>
                    </div>
                    <a v-else :href="safeUrl(item.url)" :target="item.target"
                        :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
                        class="px-3 py-2 text-[13px] font-medium rounded-lg outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-hc-accent/50"
                        :class="linkClass(false)">{{ item.label }}</a>
                </template>
            </nav>

            <!-- Right side -->
            <div class="flex items-center gap-2 ml-auto shrink-0">
                <SocialLinks class="hidden lg:flex mr-1" />
                <HeaderLanguageMenu />

                <!-- Extension-registered header icons (e.g. Shop's cart badge) -->
                <ExtensionSlot name="header.actions" />

                <button type="button"
                    class="w-8 h-8 flex items-center justify-center rounded-md border transition-colors" :class="iconButton"
                    :aria-label="dark ? t('navigation.light_mode') : t('navigation.dark_mode')"
                    :title="dark ? t('navigation.light_mode') : t('navigation.dark_mode')"
                    @click="toggleTheme">
                    <Sun v-if="dark" :size="14" :stroke-width="1.75" aria-hidden="true" />
                    <Moon v-else :size="14" :stroke-width="1.75" aria-hidden="true" />
                </button>

                <template v-if="user">
                    <HeaderNotifications :user-id="user.id ?? null" />
                    <HeaderUserMenu :user="user" />
                </template>
                <template v-else>
                    <Link v-if="page.props.registrationEnabled" :href="route('register')"
                        class="hidden px-3 py-1.5 text-[13px] font-medium rounded-md transition-colors sm:inline-flex"
                        :class="dark ? 'text-zinc-300 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100'">
                        {{ t('home.create_account') }}
                    </Link>
                    <Link :href="route('login')"
                        class="px-4 py-1.5 text-[13px] font-medium rounded-md bg-hc-accent text-hc-base hover:bg-hc-accent-hover transition-colors">
                        {{ t('home.login') }}
                    </Link>
                </template>

                <button type="button"
                    class="md:hidden w-8 h-8 flex items-center justify-center rounded-md border transition-colors"
                    :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100' : 'border-zinc-200 text-zinc-500 hover:text-zinc-900'"
                    :aria-label="mobileOpen ? t('navigation.close_menu') : t('navigation.open_menu')"
                    :aria-expanded="mobileOpen"
                    aria-controls="mobile-menu"
                    @click="mobileOpen = !mobileOpen">
                    <component :is="mobileOpen ? X : Menu" :size="15" :stroke-width="1.75" aria-hidden="true" />
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <nav v-if="mobileOpen" id="mobile-menu" class="md:hidden border-t px-4 py-3 flex flex-col gap-0.5"
            :class="dark ? 'border-zinc-800 bg-[#09090b]' : 'border-zinc-200 bg-white'"
            :aria-label="t('navigation.nav_home')">
            <a v-for="link in navLinks" :key="link.key" :href="link.href"
                :aria-current="isActive(link.href) ? 'page' : undefined"
                class="flex items-center gap-2.5 px-3 py-2.5 text-[13px] rounded-md transition-colors" :class="mobileLink"
                @click="mobileOpen = false">
                <component :is="link.icon" :size="14" :stroke-width="1.75" aria-hidden="true" />
                {{ t('navigation.' + link.key) }}
            </a>
            <a v-for="item in publicNav" :key="item.url" :href="safeUrl(item.url)"
                class="flex items-center gap-2.5 px-3 py-2.5 text-[13px] rounded-md transition-colors" :class="mobileLink"
                @click="mobileOpen = false">
                <component :is="publicNavIcon(item.icon)" :size="14" :stroke-width="1.75" aria-hidden="true" />
                {{ item.label }}
            </a>
            <!-- The admin's header menu used to be missing on phones entirely. -->
            <template v-for="item in headerMenu" :key="item.label">
                <a v-for="entry in item.children?.length ? item.children : [item]" :key="entry.label" :href="safeUrl(entry.url)" :target="entry.target"
                    :rel="entry.target === '_blank' ? 'noopener noreferrer' : undefined"
                    class="flex items-center gap-2.5 px-3 py-2.5 text-[13px] rounded-md transition-colors" :class="mobileLink"
                    @click="mobileOpen = false">{{ entry.label }}</a>
            </template>
            <div class="flex items-center gap-3 pt-3 mt-1 border-t" :class="dark ? 'border-zinc-800' : 'border-zinc-200'">
                <template v-if="!user">
                    <Link :href="route('login')" class="px-4 py-1.5 text-sm font-medium rounded-md bg-hc-accent text-hc-base hover:bg-hc-accent-hover transition-colors">{{ t('home.login') }}</Link>
                    <Link v-if="page.props.registrationEnabled" :href="route('register')" class="text-sm font-medium transition-colors" :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">{{ t('home.create_account') }}</Link>
                </template>
                <template v-else>
                    <Link :href="route('account.index')" class="flex items-center gap-2 text-sm" :class="dark ? 'text-zinc-400' : 'text-zinc-500'" @click="mobileOpen = false">
                        <User :size="14" :stroke-width="1.8" aria-hidden="true" />
                        {{ t('account.my_account') }}
                    </Link>
                    <button type="button" class="flex items-center gap-2 text-sm text-red-400 ml-auto" @click="logout">
                        <LogOut :size="14" :stroke-width="1.8" aria-hidden="true" />
                        {{ t('account.sign_out') }}
                    </button>
                </template>
            </div>
        </nav>
    </header>
</template>
