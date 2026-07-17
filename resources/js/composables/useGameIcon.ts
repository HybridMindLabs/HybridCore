import { ref, watch, type Ref } from 'vue';

/**
 * Resolves a game icon by slug, trying formats in preference order until one
 * loads — WebP first (smallest), then the common raster formats. A game's icon
 * can be dropped in as any of these and it just works; when none exist, `failed`
 * flips so the caller can show a placeholder.
 *
 * The browser only requests the next format if the previous 404s, so an all-WebP
 * install makes exactly one request per icon.
 */
const EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg', 'gif'] as const;

export function useGameIcon(slug: string | Ref<string>, size: 16 | 32 | 64 = 64) {
    const slugRef = typeof slug === 'string' ? ref(slug) : slug;

    const index = ref(0);
    const failed = ref(false);
    const src = ref('');

    function urlFor(ext: string): string {
        return `/images/games/icons/${size}x${size}/${slugRef.value}.${ext}`;
    }

    function reset(): void {
        index.value = 0;
        failed.value = false;
        src.value = urlFor(EXTENSIONS[0]);
    }

    function onError(): void {
        index.value += 1;
        if (index.value < EXTENSIONS.length) {
            src.value = urlFor(EXTENSIONS[index.value]);
        } else {
            failed.value = true;
        }
    }

    reset();
    watch(slugRef, reset);

    return { src, onError, failed };
}
