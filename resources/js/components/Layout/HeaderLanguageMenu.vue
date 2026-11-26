<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import FlagIcon from '@/components/UI/FlagIcon.vue';
import { useClickOutside } from '@/composables/useClickOutside';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';

/**
 * The flag is decoration only; the label is the language's own name, because
 * a flag names a country, not a language. Each option carries lang so screen
 * readers pronounce it correctly.
 */
const { t, currentLocale, supportedLocales, switcherEnabled, isCurrentLocale, switchLocale } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const open = ref(false);
const root = ref<HTMLElement | null>(null);
useClickOutside(root, () => { open.value = false; });

function select(code: string) {
    open.value = false;
    switchLocale(code);
}
</script>

<template>
    <div v-if="switcherEnabled && supportedLocales.length > 1" ref="root" class="relative">
        <button type="button"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :aria-label="t('navigation.language')"
            :title="t('navigation.language')"
            class="flex items-center gap-2 px-2.5 h-9 text-[12px] rounded-lg border outline-none transition-all duration-200 uppercase tracking-wide font-semibold focus-visible:ring-2 focus-visible:ring-hc-accent/50"
            :class="dark
                ? 'border-zinc-800 text-zinc-300 hover:text-white hover:bg-zinc-800/60 hover:border-zinc-700'
                : 'border-zinc-200 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 hover:border-zinc-300'"
            @click.stop="open = !open"
            @keydown.escape="open = false">
            <FlagIcon :code="currentLocale" />
            {{ currentLocale }}
            <ChevronDown :size="11" :stroke-width="2.5" class="transition-transform duration-200" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
        </button>

        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 -translate-y-1">
            <div v-if="open" role="listbox" :aria-label="t('navigation.language')"
                class="absolute right-0 top-full mt-1.5 w-52 rounded-xl border shadow-xl py-1.5 z-50"
                :class="dark ? 'bg-zinc-900/95 backdrop-blur-xl border-zinc-800 shadow-black/50' : 'bg-white/95 backdrop-blur-xl border-zinc-200 shadow-zinc-300/40'"
                @keydown.escape="open = false">
                <button v-for="locale in supportedLocales" :key="locale.code"
                    type="button" role="option"
                    :aria-selected="isCurrentLocale(locale.code)"
                    :lang="locale.code"
                    class="flex items-center gap-2.5 w-full px-3 py-2 text-[13px] outline-none transition-colors focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-hc-accent/50"
                    :class="isCurrentLocale(locale.code)
                        ? 'text-hc-accent bg-hc-accent/10'
                        : dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'"
                    @click.stop="select(locale.code)">
                    <FlagIcon :code="locale.code" />
                    <span class="font-medium">{{ locale.native_name }}</span>
                    <Check v-if="isCurrentLocale(locale.code)" :size="13" :stroke-width="2.5" class="ml-auto" aria-hidden="true" />
                </button>
            </div>
        </transition>
    </div>
</template>
