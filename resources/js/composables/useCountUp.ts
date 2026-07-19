import { ref, watch, onUnmounted, type Ref } from 'vue';

/**
 * Animates a number between values when the target changes, so live stats tick
 * over instead of jumping.
 *
 * It deliberately does NOT animate up from zero on first render. Under SSR the
 * server has no window, rendered the final figure, and the client then started
 * its animation at 0 — every stat on the page was a hydration mismatch. Showing
 * the real number immediately is also the more honest first paint: the value is
 * already known by the time anything is on screen.
 *
 * Honours prefers-reduced-motion: users who ask for less motion get the final
 * value immediately rather than a ticking counter.
 */
export function useCountUp(target: Ref<number>, duration = 900): Ref<number> {
    const displayed = ref(target.value);
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

    // No `immediate` — the first value is already in `displayed`, and animating
    // it during setup is exactly what broke hydration.
    watch(target, run);
    onUnmounted(stop);

    return displayed;
}
