<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { BadgeCheck, ChevronDown, LogOut, ShieldCheck, Star, User, UserCircle, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useClickOutside } from '@/composables/useClickOutside';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { safeUrl } from '@/lib/safeUrl';
import { publicNavIcon, usePublicNav, type PublicUser } from './usePublicNav';

const props = defineProps<{ user: PublicUser }>();

const { t } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const { userMenu } = usePublicNav();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
useClickOutside(root, () => { open.value = false; });

function logout() {
    open.value = false;
    router.post(route('logout'));
}

const links = computed(() => [
    { href: route('account.index'), icon: User, label: t('account.my_account') },
    ...(props.user.username ? [{ href: route('profile.show', props.user.username), icon: UserCircle, label: t('account.view_public_profile') }] : []),
    { href: route('account.favorites'), icon: Star, label: t('home.my_servers') },
    { href: route('members.index'), icon: Users, label: t('members.title') },
]);

const item = computed(() => dark.value ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50');
const initial = computed(() => (props.user.name.trim()[0] ?? '?').toUpperCase());
</script>

<template>
    <div ref="root" class="relative">
        <button type="button"
            class="flex items-center gap-2 pl-1.5 pr-3 py-1.5 rounded-md border transition-colors"
            :class="dark ? 'border-zinc-800 hover:bg-zinc-800/60' : 'border-zinc-200 bg-white hover:bg-zinc-50'"
            :aria-label="t('navigation.account_menu')"
            :aria-expanded="open"
            aria-haspopup="menu"
            @click.stop="open = !open"
            @keydown.escape="open = false">
            <span class="w-6 h-6 rounded-md overflow-hidden shrink-0 flex items-center justify-center" :class="dark ? 'bg-zinc-800' : 'bg-hc-accent/10'">
                <img v-if="user.avatar" :src="user.avatar" alt="" class="w-full h-full object-cover" />
                <span v-else class="text-[10px] font-bold text-hc-accent" aria-hidden="true">{{ initial }}</span>
            </span>
            <span class="hidden sm:block text-[13px] truncate max-w-[90px]" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ user.name }}</span>
            <ChevronDown :size="11" :stroke-width="2.5" class="transition-transform hidden sm:block" :class="[open ? 'rotate-180' : '', dark ? 'text-zinc-500' : 'text-zinc-400']" aria-hidden="true" />
        </button>

        <div v-if="open"
            class="absolute right-0 top-full mt-1.5 w-64 rounded-xl border shadow-xl overflow-hidden z-50"
            :class="dark ? 'bg-zinc-900 border-zinc-800 shadow-black/50' : 'bg-white border-zinc-200'"
            @keydown.escape="open = false">
            <!-- Identity header -->
            <div class="relative px-4 pt-4 pb-3.5 overflow-hidden" :class="dark ? 'bg-zinc-800/40' : 'bg-zinc-50'">
                <div class="absolute inset-0 opacity-60" aria-hidden="true" :style="{ background: 'radial-gradient(circle at 15% -10%, rgb(59 130 246 / 0.25), transparent 55%)' }" />
                <div class="relative flex items-center gap-3">
                    <span class="w-11 h-11 rounded-full overflow-hidden shrink-0 flex items-center justify-center ring-2" :class="dark ? 'bg-zinc-800 ring-zinc-900' : 'bg-hc-accent/10 ring-white'">
                        <img v-if="user.avatar" :src="user.avatar" alt="" class="w-full h-full object-cover" />
                        <span v-else class="text-[15px] font-bold text-hc-accent" aria-hidden="true">{{ initial }}</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[13.5px] font-semibold truncate flex items-center gap-1.5" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ user.name }}
                            <BadgeCheck v-if="user.verified" :size="13" class="text-hc-accent shrink-0" aria-hidden="true" />
                        </p>
                        <p v-if="user.username" class="text-[11px] font-mono truncate" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">@{{ user.username }}</p>
                    </div>
                </div>
                <span v-if="user.is_admin" class="relative mt-2.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide bg-hc-accent/15 text-hc-accent ring-1 ring-hc-accent/25">
                    <ShieldCheck :size="10" :stroke-width="2.5" aria-hidden="true" />
                    {{ t('home.administrator') }}
                </span>
            </div>

            <div class="py-1.5">
                <Link v-for="link in links" :key="link.href" :href="link.href"
                    class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="item"
                    @click="open = false">
                    <component :is="link.icon" :size="14" :stroke-width="1.8" aria-hidden="true" />
                    {{ link.label }}
                </Link>
                <!-- Extension-registered user-menu links -->
                <a v-for="entry in userMenu" :key="entry.url" :href="safeUrl(entry.url)"
                    class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="item"
                    @click="open = false">
                    <component :is="publicNavIcon(entry.icon)" :size="14" :stroke-width="1.8" aria-hidden="true" />
                    {{ entry.label }}
                </a>
            </div>

            <div v-if="user.can_access_admin" class="border-t py-1.5" :class="dark ? 'border-zinc-800' : 'border-zinc-100'">
                <Link :href="route('admin.dashboard')"
                    class="flex items-center gap-2.5 px-4 py-2 text-[13px] font-medium transition-colors w-full text-hc-accent hover:text-hc-accent-hover"
                    :class="dark ? 'hover:bg-zinc-800' : 'hover:bg-hc-accent/10'"
                    @click="open = false">
                    <ShieldCheck :size="14" :stroke-width="1.8" aria-hidden="true" />
                    {{ t('home.admin_panel') }}
                </Link>
            </div>

            <div class="border-t py-1.5" :class="dark ? 'border-zinc-800' : 'border-zinc-100'">
                <button type="button" class="flex items-center gap-2.5 px-4 py-2 text-[13px] w-full text-red-400 hover:text-red-300 transition-colors"
                    :class="dark ? 'hover:bg-zinc-800' : 'hover:bg-red-50'" @click="logout">
                    <LogOut :size="14" :stroke-width="1.8" aria-hidden="true" />
                    {{ t('account.sign_out') }}
                </button>
            </div>
        </div>
    </div>
</template>
