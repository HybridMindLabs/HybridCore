import { ref } from 'vue';

const open = ref(false);

export function useAdminSearch() {
    return {
        open,
        show: () => { open.value = true; },
        hide: () => { open.value = false; },
    };
}
