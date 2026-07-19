<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';

interface OAuthProvider { id: string; name: string; icon: string; enabled: boolean }

const { t } = useLocale();
const page = usePage<{ oauthProviders: OAuthProvider[]; [key: string]: unknown }>();

const enabledProviders = computed(() => (page.props.oauthProviders ?? []).filter((p) => p.enabled));

function signInWith(providerId: string) {
    window.location.href = route('oauth.redirect', providerId);
}
</script>

<template>
    <div v-if="enabledProviders.length > 0" class="flex flex-col gap-3">
        <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800/80" />
            <span class="text-[11px] font-medium text-zinc-400 dark:text-zinc-500">{{ t('auth.or_continue_with') }}</span>
            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800/80" />
        </div>

        <div class="grid gap-2.5" :class="enabledProviders.length > 1 ? 'grid-cols-2' : 'grid-cols-1'">
            <button
                v-for="provider in enabledProviders"
                :key="provider.id"
                type="button"
                class="flex items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-[13px] font-semibold transition-colors
                       border-zinc-200 bg-white text-zinc-500 hover:bg-zinc-50 hover:border-zinc-300
                       dark:border-zinc-800 dark:bg-zinc-900/50 dark:text-zinc-300 dark:hover:bg-zinc-800/60 dark:hover:border-zinc-700"
                @click="signInWith(provider.id)"
            >
                <svg v-if="provider.id === 'discord'" viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-[#5865f2]" fill="currentColor">
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.033.054a19.9 19.9 0 0 0 5.993 3.03.077.077 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z" />
                </svg>
                <svg v-else-if="provider.id === 'steam'" viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-[#1b2838] dark:text-[#c7d5e0]" fill="currentColor">
                    <path d="M12 2C6.477 2 2 6.477 2 12c0 4.236 2.636 7.855 6.356 9.312-.088-.791-.167-2.005.035-2.868.181-.78 1.172-4.97 1.172-4.97s-.299-.598-.299-1.482c0-1.388.806-2.428 1.808-2.428.853 0 1.267.641 1.267 1.408 0 .858-.546 2.141-.828 3.329-.236.995.499 1.806 1.476 1.806 1.772 0 3.137-1.868 3.137-4.566 0-2.387-1.716-4.055-4.165-4.055-2.837 0-4.501 2.128-4.501 4.326 0 .857.33 1.776.741 2.279a.3.3 0 0 1 .069.283c-.076.309-.244.995-.277 1.134-.044.183-.146.222-.337.134-1.249-.581-2.03-2.407-2.03-3.874 0-3.154 2.292-6.052 6.608-6.052 3.469 0 6.165 2.473 6.165 5.776 0 3.447-2.173 6.22-5.19 6.22-1.013 0-1.967-.527-2.292-1.148l-.623 2.378c-.226.869-.835 1.958-1.244 2.621.937.29 1.931.446 2.962.446 5.523 0 10-4.477 10-10S17.523 2 12 2z" />
                </svg>
                {{ provider.name }}
            </button>
        </div>
    </div>
</template>
