import { onMounted, onUnmounted } from 'vue';

type ShortcutMap = Record<string, (e: KeyboardEvent) => void>;

export function useKeyboardShortcuts(shortcuts: ShortcutMap) {
    function handler(e: KeyboardEvent) {
        const tag = (e.target as HTMLElement)?.tagName ?? '';
        const editable = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
            || (e.target as HTMLElement)?.isContentEditable;

        // Allow Escape from anywhere; block other shortcuts while typing
        if (editable && e.key !== 'Escape') return;

        const key = [
            e.ctrlKey  ? 'ctrl+'  : '',
            e.metaKey  ? 'meta+'  : '',
            e.shiftKey ? 'shift+' : '',
            e.altKey   ? 'alt+'   : '',
            e.key,
        ].join('');

        if (shortcuts[key]) {
            e.preventDefault();
            shortcuts[key](e);
        }
    }

    onMounted(()   => document.addEventListener('keydown', handler));
    onUnmounted(() => document.removeEventListener('keydown', handler));
}
