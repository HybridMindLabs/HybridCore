import { ref, readonly } from 'vue';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

export interface Toast {
    id: number;
    message: string;
    type: ToastType;
}

const toasts = ref<Toast[]>([]);
let seq = 0;

export function useToast() {
    function toast(message: string, type: ToastType = 'success', duration = 4000) {
        const id = ++seq;
        toasts.value.push({ id, message, type });
        setTimeout(() => dismiss(id), duration);
    }

    function dismiss(id: number) {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }

    return { toasts: readonly(toasts), toast, dismiss };
}
