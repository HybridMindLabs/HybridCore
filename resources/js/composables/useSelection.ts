import { computed, ref } from 'vue';

/** Checkbox selection for admin tables with bulk actions. */
export function useSelection(selectableIds: () => number[]) {
    const selected = ref<number[]>([]);

    const allSelected = computed(() => {
        const ids = selectableIds();
        return ids.length > 0 && ids.every((id) => selected.value.includes(id));
    });

    function toggleAll() {
        selected.value = allSelected.value ? [] : selectableIds();
    }

    function toggleOne(id: number) {
        selected.value = selected.value.includes(id)
            ? selected.value.filter((x) => x !== id)
            : [...selected.value, id];
    }

    return { selected, allSelected, toggleAll, toggleOne };
}
