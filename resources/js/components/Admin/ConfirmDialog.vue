<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { AlertTriangle, HelpCircle } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';

const { state, settle } = useConfirm();
const confirmButton = ref<HTMLButtonElement | null>(null);

watch(state, async (value) => {
    if (value) {
        await nextTick();
        confirmButton.value?.focus();
    }
});

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        e.preventDefault();
        settle(false);
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="state"
                class="fixed inset-0 z-[110] flex items-center justify-center px-4"
                @keydown="onKeydown"
            >
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @mousedown="settle(false)" />
                <div
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="confirm-dialog-title"
                    aria-describedby="confirm-dialog-message"
                    class="relative w-full max-w-md rounded-2xl border border-zinc-800 bg-admin-surface shadow-2xl p-5"
                >
                    <div class="flex gap-4">
                        <div
                            class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center"
                            :class="state.danger ? 'bg-red-500/10 text-red-400' : 'bg-blue-500/10 text-blue-400'"
                        >
                            <AlertTriangle v-if="state.danger" :size="18" :stroke-width="2" />
                            <HelpCircle v-else :size="18" :stroke-width="2" />
                        </div>
                        <div class="min-w-0">
                            <h2 id="confirm-dialog-title" class="text-sm font-semibold text-zinc-100">{{ state.title }}</h2>
                            <p id="confirm-dialog-message" class="mt-1 text-sm text-zinc-400 whitespace-pre-line break-words">{{ state.message }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-zinc-300 border border-zinc-800 hover:bg-admin-elevated transition-colors"
                            @click="settle(false)"
                        >
                            {{ state.cancelLabel }}
                        </button>
                        <button
                            ref="confirmButton"
                            type="button"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition-colors focus:outline-none focus-visible:ring-2"
                            :class="state.danger
                                ? 'bg-red-600 hover:bg-red-500 focus-visible:ring-red-500/40'
                                : 'bg-blue-500 hover:bg-blue-400 focus-visible:ring-blue-500/40'"
                            @click="settle(true)"
                        >
                            {{ state.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
