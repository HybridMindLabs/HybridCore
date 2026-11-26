<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import { csrfHeaders } from '@/lib/csrf';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import type { GameServer } from './types';

/** Star toggle for one server. Flips instantly and rolls back if the request fails. */
const props = withDefaults(defineProps<{ server: GameServer; size?: 'sm' | 'md' }>(), { size: 'md' });

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ auth: { user: unknown } | null }>();

const busy = ref(false);

async function toggle() {
    // Guests have no favourites; send them to log in instead of failing quietly.
    if (!page.props.auth?.user) {
        router.visit(route('login'));
        return;
    }

    const server = props.server;
    const previous = server.is_favourited;
    server.is_favourited = !previous;
    busy.value = true;

    try {
        const res = await fetch(route('servers.favourite', server.id), {
            method: 'POST',
            headers: { ...csrfHeaders(), Accept: 'application/json' },
        });
        if (!res.ok) throw new Error('request failed');
        const data = await res.json();
        server.is_favourited = !!data.favourited;
    } catch {
        server.is_favourited = previous;
    } finally {
        busy.value = false;
    }
}

const label = computed(() => t(props.server.is_favourited ? 'home.unfavourite_server' : 'home.favourite_server', { name: props.server.name }));
</script>

<template>
    <button
        type="button"
        class="flex items-center justify-center transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 disabled:opacity-50"
        :class="[
            size === 'sm' ? 'w-7 h-7 rounded-md' : 'w-8 h-8 rounded-lg',
            server.is_favourited
                ? 'text-amber-400'
                : dark ? 'text-zinc-500 hover:text-amber-400 hover:bg-white/[0.06]' : 'text-zinc-400 hover:text-amber-500 hover:bg-zinc-200/70',
        ]"
        :aria-pressed="server.is_favourited"
        :aria-label="label"
        :title="label"
        :disabled="busy"
        @click="toggle"
    >
        <Star :size="size === 'sm' ? 14 : 15" :stroke-width="1.9" :fill="server.is_favourited ? 'currentColor' : 'none'" aria-hidden="true" />
    </button>
</template>
