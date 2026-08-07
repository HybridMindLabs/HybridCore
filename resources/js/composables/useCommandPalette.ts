import { ref } from 'vue';

const open = ref(false);

export function useCommandPalette() {
    function show() {
        open.value = true;
    }

    function hide() {
        open.value = false;
    }

    return { open, show, hide };
}
