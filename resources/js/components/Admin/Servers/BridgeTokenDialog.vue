<script setup lang="ts">
import { Check, Copy, Plug } from '@lucide/vue';
import { useClipboard } from '@/composables/useClipboard';

/** The freshly generated bridge token, shown exactly once. */
defineProps<{ token: string }>();
const emit = defineEmits<{ close: [] }>();

const { copied, copy } = useClipboard(2000);
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4" role="dialog" aria-modal="true" aria-labelledby="bridge-token-title">
        <div class="w-full max-w-lg bg-admin-surface border border-zinc-800 rounded-2xl p-6">
            <h3 id="bridge-token-title" class="text-zinc-100 text-base font-bold flex items-center gap-2">
                <Plug :size="16" :stroke-width="1.75" class="text-emerald-400" aria-hidden="true" /> Bridge token generated
            </h3>
            <p class="text-zinc-500 text-xs mt-1 leading-relaxed">
                Paste this token into the in-game plugin config. For security it is stored hashed —
                <span class="text-amber-400 font-semibold">this is the only time it will be shown</span>.
                If you lose it, generate a new one.
            </p>

            <div class="flex items-center gap-2 mt-4 bg-zinc-900/70 border border-zinc-800 rounded-lg px-3 py-2.5">
                <code class="flex-1 text-[13px] text-emerald-300 font-mono break-all select-all">{{ token }}</code>
                <button type="button"
                    class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-500 transition-colors"
                    :title="copied ? 'Copied' : 'Copy token'" :aria-label="copied ? 'Copied' : 'Copy token'"
                    @click="copy(token)">
                    <component :is="copied ? Check : Copy" :size="13" :stroke-width="1.75" :class="copied ? 'text-emerald-400' : ''" />
                </button>
            </div>

            <p class="text-zinc-500 text-[11px] mt-3 font-mono">POST /api/bridge/poll · Authorization: Bearer &lt;token&gt;</p>

            <button type="button"
                class="mt-4 w-full bg-blue-500 text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-blue-400 transition-colors"
                @click="emit('close')">
                I saved the token
            </button>
        </div>
    </div>
</template>
