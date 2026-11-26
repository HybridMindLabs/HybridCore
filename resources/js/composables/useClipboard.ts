import { onUnmounted, ref } from 'vue';

/**
 * Copy text, falling back to a hidden textarea where the Clipboard API is
 * missing — it needs a secure context, so over plain http it throws.
 */
export async function copyText(text: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        return;
    } catch {
        // fall through to the legacy path
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    try {
        document.execCommand('copy');
    } finally {
        document.body.removeChild(field);
    }
}

/** A copy action with a short-lived "copied" flag for the button's feedback. */
export function useClipboard(resetAfterMs = 1600) {
    const copied = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;

    async function copy(text: string) {
        await copyText(text);
        copied.value = true;
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => { copied.value = false; }, resetAfterMs);
    }

    onUnmounted(() => { if (timer) clearTimeout(timer); });

    return { copied, copy };
}
