import { ref } from 'vue';

const mobileOpen = ref(false);

export function useAdminSidebar() {
    function open() {
        mobileOpen.value = true;
    }

    function close() {
        mobileOpen.value = false;
    }

    function toggle() {
        mobileOpen.value = !mobileOpen.value;
    }

    return { mobileOpen, open, close, toggle };
}
