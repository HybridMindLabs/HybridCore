<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Wifi } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { initial, type Presence } from './types';

const props = defineProps<{
    online: Presence | null;
    today: Presence | null;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

// A busy community pushes dozens of names into a ~250px column, which turns
// the widget into an endless list and buries everything under it. Show a
// readable handful and send the rest to the members page.
const PRESENCE_VISIBLE = 12;

const groups = computed(() => [
    {
        key: 'online',
        label: t('home.who_is_online'),
        hint: t('home.last_5_minutes'),
        data: props.online,
        empty: t('home.no_registered_online'),
        emptyIcon: Wifi,
        live: true,
        countClass: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-400',
    },
    {
        key: 'today',
        label: t('home.active_last_24h'),
        hint: '',
        data: props.today,
        empty: t('home.no_active_users'),
        emptyIcon: Clock,
        live: false,
        countClass: 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400',
    },
].map((group) => {
    const users = group.data?.users ?? [];

    return {
        ...group,
        visibleUsers: users.slice(0, PRESENCE_VISIBLE),
        overflowCount: Math.max(0, users.length - PRESENCE_VISIBLE),
    };
}));
</script>

<template>
    <div class="hc-reveal rounded-xl border overflow-hidden" style="animation-delay:0.1s"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">

        <section v-for="(group, gi) in groups" :key="group.key"
            :class="gi > 0 ? (dark ? 'border-t border-zinc-800/60' : 'border-t border-zinc-200') : ''"
            :aria-label="group.label">

            <div class="flex items-center gap-2 px-3.5 py-2.5 border-b"
                :class="dark ? 'bg-[#17171a] border-zinc-800/60' : 'bg-zinc-50 border-zinc-200'">
                <span v-if="group.live" class="hc-live-dot" aria-hidden="true" />
                <Clock v-else :size="11" :stroke-width="2.2" class="text-zinc-500" aria-hidden="true" />
                <h3 class="text-[10.5px] font-black uppercase tracking-widest flex-1"
                    :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ group.label }}</h3>
                <span v-if="group.data?.users.length"
                    class="px-2 py-0.5 rounded-full text-[11px] font-bold border tabular-nums"
                    :class="group.countClass">
                    {{ group.data.users.length }}
                </span>
            </div>

            <div class="px-3.5 py-3">
                <!-- Guests and bots: context for the member count above -->
                <div v-if="group.data?.guests || group.data?.bots || group.hint"
                    class="flex items-center gap-2 flex-wrap mb-2.5 text-[10.5px] text-zinc-500">
                    <span v-if="group.data?.guests">{{ t('home.guests_count', { count: group.data.guests }) }}</span>
                    <span v-if="group.data?.bots">{{ t('home.bots_count', { count: group.data.bots }) }}</span>
                    <span v-if="group.hint" class="ml-auto">{{ group.hint }}</span>
                </div>

                <ul v-if="group.visibleUsers.length" class="flex flex-wrap gap-1.5">
                    <li v-for="u in group.visibleUsers" :key="u.id">
                        <component :is="u.username ? Link : 'span'"
                            :href="u.username ? route('profile.show', { username: u.username }) : undefined"
                            class="group/user flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-full border text-[11.5px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'border-zinc-800 bg-zinc-900/60 text-zinc-300 hover:text-blue-400 hover:border-zinc-700' : 'border-zinc-200 bg-zinc-50 text-zinc-500 hover:text-blue-600 hover:border-zinc-300'"
                            :title="u.username ? '@' + u.username : u.name">
                            <span class="w-[18px] h-[18px] rounded-full overflow-hidden shrink-0 flex items-center justify-center text-[9px] font-black"
                                :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-500'">
                                <!-- alt is empty on purpose: the name is right beside it,
                                     so announcing the avatar too would just repeat it. -->
                                <img v-if="u.avatar" :src="u.avatar" alt="" loading="lazy" decoding="async"
                                    class="w-full h-full object-cover" />
                                <span v-else aria-hidden="true">{{ initial(u.name) }}</span>
                            </span>
                            <!-- Narrow enough that two chips share a row in the sidebar
                                 instead of each name claiming its own line. -->
                            <span class="truncate max-w-[68px]">{{ u.name }}</span>
                        </component>
                    </li>

                    <li v-if="group.overflowCount">
                        <Link :href="route('members.index')"
                            class="flex items-center px-2.5 py-1 rounded-full border border-dashed text-[11.5px] font-bold tabular-nums transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'border-zinc-700 text-zinc-400 hover:text-blue-400 hover:border-blue-500/40' : 'border-zinc-300 text-zinc-500 hover:text-blue-600 hover:border-blue-400/50'">
                            {{ t('home.presence_more', { count: group.overflowCount }) }}
                        </Link>
                    </li>
                </ul>
                <p v-else class="flex items-center gap-1.5 text-[11.5px] text-zinc-500">
                    <component :is="group.emptyIcon" :size="13" :stroke-width="1.8" aria-hidden="true" />
                    {{ group.empty }}
                </p>
            </div>
        </section>
    </div>
</template>
