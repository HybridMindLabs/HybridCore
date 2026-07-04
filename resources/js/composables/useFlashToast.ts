import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast';

export function useFlashToast() {
    const page = usePage<{ flash?: { success?: string | null; error?: string | null } }>();
    const { toast } = useToast();

    watch(
        () => page.props.flash,
        (flash) => {
            if (flash?.success) toast(flash.success, 'success');
            if (flash?.error)   toast(flash.error,   'error');
        },
        { immediate: true, deep: true },
    );
}
