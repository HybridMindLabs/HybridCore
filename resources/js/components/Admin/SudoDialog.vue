<script setup lang="ts">
import { LockKeyhole } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import { useSudo } from '@/composables/useSudo';

const { open, settle } = useSudo();
const password = ref('');
const error = ref('');
const busy = ref(false);
const input = ref<HTMLInputElement | null>(null);

watch(open, async (isOpen) => {
    password.value = '';
    error.value = '';
    if (isOpen) {
        await nextTick();
        input.value?.focus();
    }
});

async function submit() {
    if (!password.value || busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const res = await fetch(route('admin.password.confirm.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ password: password.value }),
        });

        if (res.ok) {
            settle(true);
            return;
        }

        error.value = res.status === 429
            ? 'Too many attempts. Wait a minute and try again.'
            : ((await res.json().catch(() => null))?.errors?.password?.[0] ?? 'That password is incorrect.');
        password.value = '';
        input.value?.focus();
    } finally {
        busy.value = false;
    }
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        e.preventDefault();
        settle(false);
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[115] flex items-center justify-center px-4" @keydown="onKeydown">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @mousedown="settle(false)" />
            <form
                role="dialog"
                aria-modal="true"
                aria-labelledby="sudo-title"
                class="relative w-full max-w-sm rounded-2xl border border-zinc-800 bg-admin-surface shadow-2xl p-5"
                @submit.prevent="submit"
            >
                <div class="flex gap-4">
                    <div class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center bg-amber-500/10 text-amber-400">
                        <LockKeyhole :size="18" :stroke-width="2" />
                    </div>
                    <div class="min-w-0">
                        <h2 id="sudo-title" class="text-sm font-semibold text-zinc-100">Confirm your password</h2>
                        <p class="mt-1 text-sm text-zinc-400">This action is protected. You won't be asked again for 15 minutes.</p>
                    </div>
                </div>

                <label for="sudo-password" class="sr-only">Password</label>
                <input
                    id="sudo-password"
                    ref="input"
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Your password"
                    class="mt-4 w-full bg-zinc-900/60 border text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 placeholder:text-zinc-600"
                    :class="error ? 'border-red-500' : 'border-zinc-800/70'"
                    :aria-invalid="!!error"
                    aria-describedby="sudo-error"
                />
                <p id="sudo-error" class="mt-1.5 min-h-4 text-xs text-red-400">{{ error }}</p>

                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 rounded-lg text-sm font-medium text-zinc-300 border border-zinc-800 hover:bg-admin-elevated transition-colors" @click="settle(false)">
                        Cancel
                    </button>
                    <button type="submit" :disabled="!password || busy" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-500 hover:bg-blue-400 transition-colors disabled:opacity-50">
                        {{ busy ? 'Checking…' : 'Confirm' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
