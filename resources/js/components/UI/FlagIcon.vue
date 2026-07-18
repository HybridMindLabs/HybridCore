<script setup lang="ts">
import { computed } from 'vue';

/**
 * A small flag for a locale, drawn inline as SVG.
 *
 * Emoji flags are not an option: Windows ships no flag glyphs, so 🇧🇬 renders as
 * the letters "BG" for a large share of visitors. These are a few hundred bytes
 * each and render identically everywhere.
 *
 * Purely decorative — the visible label is always the language name, because a
 * flag is a country and a language is not. Arabic has no flag for exactly that
 * reason and falls back to a code badge.
 */
const props = defineProps<{ code: string }>();

const flag = computed(() => props.code.toLowerCase());
</script>

<template>
    <span class="inline-flex shrink-0 rounded-[3px] overflow-hidden ring-1 ring-black/10 dark:ring-white/15" aria-hidden="true">
        <svg viewBox="0 0 30 20" class="w-[18px] h-[12px] block">
            <!-- Bulgaria -->
            <template v-if="flag === 'bg'">
                <rect width="30" height="20" fill="#fff" />
                <rect y="6.67" width="30" height="6.67" fill="#00966E" />
                <rect y="13.33" width="30" height="6.67" fill="#D62612" />
            </template>

            <!-- United Kingdom (English) -->
            <template v-else-if="flag === 'en'">
                <rect width="30" height="20" fill="#012169" />
                <path d="M0,0 L30,20 M30,0 L0,20" stroke="#fff" stroke-width="4" />
                <path d="M0,0 L30,20 M30,0 L0,20" stroke="#C8102E" stroke-width="2" />
                <path d="M15,0 V20 M0,10 H30" stroke="#fff" stroke-width="6" />
                <path d="M15,0 V20 M0,10 H30" stroke="#C8102E" stroke-width="3.5" />
            </template>

            <!-- Germany -->
            <template v-else-if="flag === 'de'">
                <rect width="30" height="20" fill="#000" />
                <rect y="6.67" width="30" height="6.67" fill="#DD0000" />
                <rect y="13.33" width="30" height="6.67" fill="#FFCE00" />
            </template>

            <!-- France -->
            <template v-else-if="flag === 'fr'">
                <rect width="30" height="20" fill="#fff" />
                <rect width="10" height="20" fill="#002395" />
                <rect x="20" width="10" height="20" fill="#ED2939" />
            </template>

            <!-- Poland -->
            <template v-else-if="flag === 'pl'">
                <rect width="30" height="20" fill="#fff" />
                <rect y="10" width="30" height="10" fill="#DC143C" />
            </template>

            <!-- Russia -->
            <template v-else-if="flag === 'ru'">
                <rect width="30" height="20" fill="#fff" />
                <rect y="6.67" width="30" height="6.67" fill="#0039A6" />
                <rect y="13.33" width="30" height="6.67" fill="#D52B1E" />
            </template>

            <!-- Turkey -->
            <template v-else-if="flag === 'tr'">
                <rect width="30" height="20" fill="#E30A17" />
                <circle cx="11" cy="10" r="5" fill="#fff" />
                <circle cx="12.5" cy="10" r="4" fill="#E30A17" />
                <path d="M17.5,10 l3.6,-1.2 -2.2,3.1 0,-3.8 2.2,3.1 z" fill="#fff" />
            </template>

            <!-- No sensible flag (a language spoken across many countries) -->
            <template v-else>
                <rect width="30" height="20" fill="currentColor" opacity="0.12" />
                <text
                    x="15" y="14" text-anchor="middle"
                    font-size="9" font-weight="700" fill="currentColor"
                >{{ code.toUpperCase() }}</text>
            </template>
        </svg>
    </span>
</template>
