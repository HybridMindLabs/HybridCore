import { onMounted, onUnmounted } from 'vue';

type ShortcutMap = Record<string, (e: KeyboardEvent) => void>;

/** How long the first key of a sequence like "g d" waits for the second. */
const SEQUENCE_TIMEOUT = 1000;

/**
 * Keys are either a chord ("ctrl+k", "Escape", "?") or a two-key sequence
 * separated by a space ("g d" = press g, then d).
 */
export function useKeyboardShortcuts(shortcuts: ShortcutMap) {
    let pending: string | null = null;
    let pendingTimer: ReturnType<typeof setTimeout> | null = null;

    function clearPending() {
        pending = null;
        if (pendingTimer) clearTimeout(pendingTimer);
        pendingTimer = null;
    }

    function handler(e: KeyboardEvent) {
        const target = e.target as HTMLElement | null;
        const tag = target?.tagName ?? '';
        const editable = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target?.isContentEditable;

        // Escape and modifier chords work from anywhere; plain keys don't fire while typing.
        if (editable && e.key !== 'Escape' && !e.ctrlKey && !e.metaKey) return;

        // Shift is implied by printable symbols like "?", so it isn't part of their name.
        const printable = e.key.length === 1;
        const key = [
            e.ctrlKey ? 'ctrl+' : '',
            e.metaKey ? 'meta+' : '',
            e.shiftKey && !printable ? 'shift+' : '',
            e.altKey ? 'alt+' : '',
            printable ? e.key.toLowerCase() : e.key,
        ].join('');

        if (pending) {
            const sequence = `${pending} ${key}`;
            clearPending();
            if (shortcuts[sequence]) {
                e.preventDefault();
                shortcuts[sequence](e);
                return;
            }
        }

        if (shortcuts[key]) {
            e.preventDefault();
            shortcuts[key](e);
            return;
        }

        if (Object.keys(shortcuts).some((s) => s.startsWith(`${key} `))) {
            pending = key;
            pendingTimer = setTimeout(clearPending, SEQUENCE_TIMEOUT);
        }
    }

    onMounted(() => document.addEventListener('keydown', handler));
    onUnmounted(() => {
        document.removeEventListener('keydown', handler);
        clearPending();
    });
}
