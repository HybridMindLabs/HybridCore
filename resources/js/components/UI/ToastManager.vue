<script setup lang="ts">
import { CheckCircle, XCircle, Info, AlertTriangle, X } from '@lucide/vue';
import { useToast } from '@/composables/useToast';
import { computed } from 'vue';

const { toasts, dismiss } = useToast();

const icons = { success: CheckCircle, error: XCircle, info: Info, warning: AlertTriangle };
const styles = {
    success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
    error:   'border-red-500/30 bg-red-500/10 text-red-300',
    info:    'border-blue-500/30 bg-blue-500/10 text-blue-300',
    warning: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
};
const iconColors = {
    success: 'text-emerald-400',
    error:   'text-red-400',
    info:    'text-blue-400',
    warning: 'text-amber-400',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none">
            <TransitionGroup name="toast">
                <div v-for="t in toasts" :key="t.id"
                    class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl border shadow-2xl backdrop-blur-sm min-w-[280px] max-w-[380px]"
                    :class="styles[t.type]">
                    <component :is="icons[t.type]" :size="16" :stroke-width="2" class="shrink-0 mt-0.5" :class="iconColors[t.type]" />
                    <p class="flex-1 text-[13px] font-semibold leading-snug">{{ t.message }}</p>
                    <button type="button" class="shrink-0 opacity-50 hover:opacity-100 transition-opacity" @click="dismiss(t.id)">
                        <X :size="13" :stroke-width="2" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active { transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.toast-leave-active { transition: all 0.2s ease-in; }
.toast-enter-from   { opacity: 0; transform: translateX(20px) scale(0.95); }
.toast-leave-to     { opacity: 0; transform: translateX(20px) scale(0.95); }
</style>
