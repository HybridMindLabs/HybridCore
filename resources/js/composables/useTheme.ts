import { ref, watch, onMounted } from 'vue';

type Theme = 'dark' | 'light';

const theme = ref<Theme>('dark');

export function useTheme() {
    function applyTheme(t: Theme) {
        if (t === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    function init() {
        const stored = localStorage.getItem('hc-theme') as Theme | null;
        theme.value = stored === 'light' ? 'light' : 'dark';
        applyTheme(theme.value);
    }

    function toggle() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
        localStorage.setItem('hc-theme', theme.value);
        applyTheme(theme.value);
    }

    watch(theme, applyTheme);

    return { theme, toggle, init };
}
