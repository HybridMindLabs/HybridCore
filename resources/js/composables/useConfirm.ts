import { readonly, ref } from 'vue';

export interface ConfirmOptions {
    title?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    danger?: boolean;
}

interface ConfirmState extends Required<ConfirmOptions> {
    message: string;
}

const DESTRUCTIVE = /^(delete|remove|revoke|ban|uninstall|permanently|clear|refund|rotate|regenerate|deactivate|suspend)\b/i;

const state = ref<ConfirmState | null>(null);
let resolver: ((value: boolean) => void) | null = null;

function settle(value: boolean) {
    resolver?.(value);
    resolver = null;
    state.value = null;
}

/**
 * Promise-based replacement for window.confirm(). The button label defaults to
 * the message's first word ("Revoke this token?" → "Revoke"), which also covers
 * bulk prompts built from an action name.
 */
export function confirm(message: string, options: ConfirmOptions = {}): Promise<boolean> {
    // A second prompt while one is open cancels the first rather than stacking.
    if (resolver) settle(false);

    const firstWord = message.match(/^[A-Za-z]+/)?.[0] ?? 'Confirm';

    state.value = {
        message,
        title: options.title ?? 'Are you sure?',
        confirmLabel: options.confirmLabel ?? firstWord.charAt(0).toUpperCase() + firstWord.slice(1),
        cancelLabel: options.cancelLabel ?? 'Cancel',
        danger: options.danger ?? DESTRUCTIVE.test(message),
    };

    return new Promise((resolve) => {
        resolver = resolve;
    });
}

export function useConfirm() {
    return { state: readonly(state), settle };
}
