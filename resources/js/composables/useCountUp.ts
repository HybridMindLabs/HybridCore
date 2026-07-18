import { ref, watch, onUnmounted, type Ref } from 'vue';

/**
 * Animates a number from 0 up to its target on mount, and between values when
 * the target changes later (live stats update without a page reload).
 *
 * Honours prefers-reduced-motion: users who ask for less motion get the final
 * value immediately rather than a ticking counter.
 */
export function useCountUp(target: Ref<number>, duration = 900): Ref<number> {
    const displayed = ref(0);
    let frame: number | null = null;

    const reduced = typeof window !== 'undefined'
        && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    function stop() {
        if (frame !== null) {
            cancelAnimationFrame(frame);
            frame = null;
        }
    }

    function run(to: number) {
        stop();

        if (reduced || typeof window === 'undefined' || duration <= 0) {
            displayed.value = to;
            return;
        }

        const from = displayed.value;
        const delta = to - from;

        if (delta === 0) return;

        const start = performance.now();

        const step = (now: number) => {
            const p = Math.min(1, (now - start) / duration);
            // easeOutExpo — fast start, gentle settle, so the number reads as
            // "landing" rather than crawling.
            const eased = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);

            displayed.value = Math.round(from + delta * eased);

            frame = p < 1 ? requestAnimationFrame(step) : null;
        };

        frame = requestAnimationFrame(step);
    }

    watch(target, run, { immediate: true });
    onUnmounted(stop);

    return displayed;
}
