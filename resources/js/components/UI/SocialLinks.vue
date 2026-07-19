<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

/**
 * The site's social links, rendered from the URLs configured in admin settings.
 *
 * Brand colours are per theme where the real one disappears against the
 * background: Steam's navy and X's black are invisible on a dark page, so each
 * platform carries the shade its own brand guide uses on dark surfaces. Every
 * value clears 3:1 against both themes' backgrounds — icons are non-text
 * content, so that is the bar they have to meet.
 */
const props = withDefaults(defineProps<{
    /** `bar` is the bare header row; `boxed` is the bordered footer tile. */
    variant?: 'bar' | 'boxed';
}>(), { variant: 'bar' });

interface Social {
    label: string;
    svg: string;
    viewBox: string;
    brand: string;
    brandDark: string;
    /**
     * Optical size. The glyphs fill their 24x24 box very differently — Discord
     * is a wide mark that reads heavy beside X, which is a thin one — so each
     * gets a nudge to make the row look evenly weighted rather than measure
     * evenly.
     */
    scale?: number;
}

const SOCIALS: Record<string, Social> = {
    discord: {
        label: 'Discord',
        brand: '#5865f2',
        brandDark: '#7f8cff',
        viewBox: '0 0 24 24',
        // The widest mark of the four, and solid rather than outlined.
        scale: 0.92,
        svg: `<path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.033.054a19.9 19.9 0 0 0 5.993 3.03.077.077 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>`,
    },
    steam: {
        label: 'Steam',
        brand: '#1b2838',
        brandDark: '#66c0f4',
        viewBox: '0 0 24 24',
        // Steam's actual mark: the offset circle with the tilted rod. What was
        // here before drew a plain circle with a curl inside it and read as a
        // Pinterest glyph.
        svg: `<path d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.031 4.524 4.527s-2.03 4.525-4.524 4.525h-.105l-4.076 2.911c0 .052.004.105.004.159 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.727L.436 15.27C1.862 20.307 6.486 24 11.979 24c6.627 0 11.999-5.373 11.999-12S18.605 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.999 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.63.264-1.319.005-1.949s-.75-1.121-1.377-1.383c-.624-.26-1.29-.249-1.878-.03l1.523.63c.956.4 1.409 1.5 1.009 2.455-.397.957-1.497 1.41-2.454 1.012H7.54zm11.415-9.303c0-1.662-1.353-3.015-3.015-3.015-1.665 0-3.015 1.353-3.015 3.015 0 1.665 1.35 3.015 3.015 3.015 1.663 0 3.015-1.35 3.015-3.015zm-5.273-.005c0-1.252 1.013-2.266 2.265-2.266 1.249 0 2.266 1.014 2.266 2.266 0 1.251-1.017 2.265-2.266 2.265-1.253 0-2.265-1.014-2.265-2.265z"/>`,
    },
    twitter: {
        label: 'X',
        brand: '#18181b',
        brandDark: '#ffffff',
        viewBox: '0 0 24 24',
        // Thin strokes and a small footprint, so it carries a little more size.
        scale: 1.06,
        svg: `<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.213 5.567 5.951-5.567zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>`,
    },
    youtube: {
        label: 'YouTube',
        brand: '#ff0000',
        brandDark: '#ff3b30',
        viewBox: '0 0 24 24',
        svg: `<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>`,
    },
};

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const page = usePage<{ socialLinks?: Record<string, string> }>();

// Only platforms with a URL configured in admin settings are rendered.
const links = computed(() => Object.entries(page.props.socialLinks ?? {})
    .filter(([key, url]) => url && SOCIALS[key])
    .map(([key, url]) => ({ ...SOCIALS[key], key, href: url })));
</script>

<template>
    <div v-if="links.length" class="flex items-center" :class="variant === 'boxed' ? 'gap-2' : 'gap-1'">
        <a
            v-for="s in links"
            :key="s.key"
            :href="s.href"
            :aria-label="s.label"
            :title="s.label"
            target="_blank"
            rel="noopener noreferrer"
            class="hc-social"
            :class="[
                variant === 'boxed' ? 'hc-social--boxed' : 'hc-social--bar',
                dark ? 'hc-social--dark' : 'hc-social--light',
            ]"
            :style="{ '--brand': dark ? s.brandDark : s.brand }"
        >
            <svg
                :viewBox="s.viewBox"
                fill="currentColor"
                aria-hidden="true"
                focusable="false"
                :style="{ '--optical': s.scale ?? 1 }"
            >
                <g v-html="s.svg" />
            </svg>
        </a>
    </div>
</template>

<style scoped>
.hc-social {
    /* 36px keeps the target comfortably over the 24px minimum, which the old
       bare 14px icon in the header was well under. */
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    color: var(--brand);
    position: relative;
    transition: transform 180ms cubic-bezier(0.34, 1.4, 0.64, 1),
                background-color 180ms ease,
                border-color 180ms ease,
                box-shadow 180ms ease;
}

.hc-social svg {
    width: 16px;
    height: 16px;
    transform: scale(var(--optical, 1));
    transition: transform 180ms cubic-bezier(0.34, 1.4, 0.64, 1);
}

/* Resting state is the brand hue held back, so a row of four does not shout;
   hover brings it to full strength. */
.hc-social--bar {
    opacity: 0.75;
}

.hc-social--boxed {
    border: 1px solid transparent;
    opacity: 0.85;
}

.hc-social--boxed.hc-social--dark {
    border-color: rgb(39 39 42 / 1);
    background-color: rgb(24 24 27 / 0.5);
}

.hc-social--boxed.hc-social--light {
    border-color: rgb(212 212 216 / 1);
    background-color: rgb(255 255 255 / 0.7);
}

.hc-social:hover,
.hc-social:focus-visible {
    opacity: 1;
    transform: translateY(-2px);
    /* colour-mix keeps one rule per state instead of a tint per platform. */
    background-color: color-mix(in srgb, var(--brand) 14%, transparent);
    border-color: color-mix(in srgb, var(--brand) 45%, transparent);
    box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--brand) 55%, transparent);
}

.hc-social:hover svg,
.hc-social:focus-visible svg {
    transform: scale(calc(var(--optical, 1) * 1.12));
}

.hc-social:active {
    transform: translateY(0);
}

.hc-social:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--brand) 70%, transparent);
    outline-offset: 2px;
}

/* Movement is the decoration; colour still carries the state for anyone who
   has asked the OS for less of it. */
@media (prefers-reduced-motion: reduce) {
    .hc-social,
    .hc-social svg {
        transition: background-color 180ms ease, border-color 180ms ease, opacity 180ms ease;
    }

    .hc-social:hover,
    .hc-social:focus-visible,
    .hc-social:active {
        transform: none;
    }

    .hc-social:hover svg,
    .hc-social:focus-visible svg {
        transform: scale(var(--optical, 1));
    }
}
</style>
