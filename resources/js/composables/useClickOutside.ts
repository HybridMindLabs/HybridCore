import { onMounted, onUnmounted, type Ref } from 'vue';

/** Run `close` when a click lands outside `target` — for dropdowns and popovers. */
export function useClickOutside(target: Ref<HTMLElement | null>, close: () => void): void {
    function onClick(e: MouseEvent) {
        if (target.value && !target.value.contains(e.target as Node)) close();
    }

    onMounted(() => document.addEventListener('click', onClick, true));
    onUnmounted(() => document.removeEventListener('click', onClick, true));
}
