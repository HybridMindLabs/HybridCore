import { readonly, ref } from 'vue';

const open = ref(false);
let resolver: ((confirmed: boolean) => void) | null = null;

/** Ask for the admin's password before a sudo-protected action; resolves true once confirmed. */
export function requireSudo(): Promise<boolean> {
    if (resolver) resolver(false);
    open.value = true;

    return new Promise((resolve) => {
        resolver = resolve;
    });
}

function settle(confirmed: boolean) {
    resolver?.(confirmed);
    resolver = null;
    open.value = false;
}

export function useSudo() {
    return { open: readonly(open), settle };
}
