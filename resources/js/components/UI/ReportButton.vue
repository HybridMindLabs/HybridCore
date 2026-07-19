<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Flag } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{
    /** Report target: 'comment' | 'review'. */
    type: string;
    id: number;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const open = ref(false);
const sending = ref(false);
const rootRef = ref<HTMLElement | null>(null);

const reasons = ['spam', 'abuse', 'off_topic', 'other'] as const;

function submit(reason: string) {
    if (sending.value) return;
    sending.value = true;
    router.post(route('report.store'), { type: props.type, id: props.id, reason }, {
        preserveScroll: true,
        onFinish: () => { sending.value = false; open.value = false; },
    });
}

function onDocClick(e: MouseEvent) {
    if (rootRef.value && !rootRef.value.contains(e.target as Node)) open.value = false;
}
onMounted(() => document.addEventListener('click', onDocClick, true));
onUnmounted(() => document.removeEventListener('click', onDocClick, true));
</script>

<template>
    <div ref="rootRef" class="relative inline-flex">
        <button
            type="button"
            class="p-1 rounded transition"
            :class="dark ? 'text-zinc-500 hover:text-amber-400' : 'text-zinc-300 hover:text-amber-500'"
            :title="t('report.report')"
            @click.stop="open = !open"
        >
            <Flag :size="12" :stroke-width="2" />
        </button>

        <div v-if="open"
            class="absolute right-0 top-full mt-1 w-44 rounded-xl border shadow-lg py-1 z-50"
            :class="dark ? 'bg-zinc-900 border-zinc-800 shadow-black/40' : 'bg-white border-zinc-200 shadow-zinc-200/60'">
            <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest"
                :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('report.pick_reason') }}</p>
            <button
                v-for="reason in reasons"
                :key="reason"
                type="button"
                :disabled="sending"
                class="block w-full text-left px-3 py-1.5 text-[12px] transition disabled:opacity-50"
                :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'"
                @click.stop="submit(reason)"
            >{{ t('report.reason_' + reason) }}</button>
        </div>
    </div>
</template>
