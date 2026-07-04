<script setup lang="ts">
import { ref, computed } from 'vue';
import { Search, ChevronDown } from '@lucide/vue';
import { ROLE_ICONS, ROLE_ICON_NAMES } from '@/constants/icons';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{ modelValue: string; color?: string }>();
const emit = defineEmits<{ 'update:modelValue': [string] }>();

const { t } = useLocale();
const open = ref(false);
const query = ref('');

const filtered = computed(() =>
    ROLE_ICON_NAMES.filter((name) => name.toLowerCase().includes(query.value.toLowerCase())),
);

function select(name: string) {
    emit('update:modelValue', name);
    open.value = false;
    query.value = '';
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="flex items-center gap-2.5 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm w-full
                   hover:border-blue-500/40 transition-colors"
            @click="open = !open"
        >
            <span
                class="w-7 h-7 rounded-md flex items-center justify-center shrink-0"
                :style="{ backgroundColor: (color ?? '#64748b') + '22', color: color ?? '#64748b' }"
            >
                <component :is="ROLE_ICONS[modelValue]" :size="15" :stroke-width="1.75" />
            </span>
            <span class="flex-1 text-left">{{ modelValue }}</span>
            <ChevronDown :size="14" :stroke-width="1.75" class="text-zinc-600" />
        </button>

        <div
            v-if="open"
            class="absolute z-20 mt-1.5 w-full bg-[#0d1420] border border-zinc-800/70 rounded-xl shadow-xl shadow-black/40 p-3"
        >
            <div class="relative mb-2.5">
                <Search :size="13" :stroke-width="1.75" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-zinc-600" />
                <input
                    v-model="query"
                    type="text"
                    :placeholder="t('roles.search_icons')"
                    class="w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg pl-8 pr-3 py-1.5 text-xs
                           focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                />
            </div>
            <div class="grid grid-cols-6 gap-1.5 max-h-56 overflow-y-auto">
                <button
                    v-for="name in filtered"
                    :key="name"
                    type="button"
                    class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors"
                    :class="name === modelValue
                        ? 'bg-blue-500/15 text-blue-400 border border-blue-500/40'
                        : 'text-zinc-400 border border-transparent hover:bg-zinc-900/60 hover:text-zinc-100'"
                    :title="name"
                    @click="select(name)"
                >
                    <component :is="ROLE_ICONS[name]" :size="16" :stroke-width="1.75" />
                </button>
            </div>
        </div>
    </div>
</template>
