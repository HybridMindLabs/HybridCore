<script setup lang="ts">
import { Globe, Check } from '@lucide/vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { useLocale } from '@/composables/useLocale';

const { currentLocale, supportedLocales, switcherEnabled, isCurrentLocale, switchLocale } = useLocale();

const open = ref(false);
const wrapperRef = ref<HTMLElement | null>(null);

function select(code: string) {
    open.value = false;
    switchLocale(code);
}

function onDocumentClick(e: MouseEvent) {
    if (wrapperRef.value && !wrapperRef.value.contains(e.target as Node)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick, true));
onUnmounted(() => document.removeEventListener('click', onDocumentClick, true));
</script>

<template>
    <div v-if="switcherEnabled && supportedLocales.length > 1" ref="wrapperRef" class="relative">
        <button
            type="button"
            class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-white/10 dark:bg-[#0b111d]/90 dark:text-[#aab7cc] dark:hover:bg-[#111a2a] dark:hover:text-zinc-100"
            @click.stop="open = !open"
        >
            <Globe :size="13" :stroke-width="1.75" />
            {{ currentLocale }}
        </button>

        <div
            v-if="open"
            class="absolute right-0 top-full z-50 mt-2 w-48 rounded-xl border border-gray-200 bg-white py-1 shadow-xl shadow-gray-200/80 dark:border-white/10 dark:bg-[#0b111d] dark:shadow-black/50"
        >
            <button
                v-for="locale in supportedLocales"
                :key="locale.code"
                type="button"
                class="flex w-full items-center justify-between px-3 py-2 text-left text-xs transition-colors"
                :class="isCurrentLocale(locale.code)
                    ? 'text-[#2563eb] dark:text-[#60a5fa]'
                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-950 dark:text-zinc-400 dark:hover:bg-[#111a2a] dark:hover:text-zinc-100'"
                @click.stop="select(locale.code)"
            >
                <span>
                    {{ locale.native_name }}
                    <span class="ml-1 text-gray-400 dark:text-zinc-600">{{ locale.flag }}</span>
                </span>
                <Check v-if="isCurrentLocale(locale.code)" :size="12" :stroke-width="2" />
            </button>
        </div>
    </div>
</template>
