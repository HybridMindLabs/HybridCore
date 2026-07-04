<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Trash2, Download, AlertTriangle } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { computed, ref } from 'vue';

const props = defineProps<{ username: string }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const showDeleteConfirm = ref(false);

const deleteForm = useForm({ username_confirm: '', password: '' });
const exportForm = useForm({ password: '' });

function submitDelete() {
    deleteForm.post(route('account.delete'));
}
function submitExport() {
    exportForm.post(route('account.export'), { onSuccess: () => exportForm.reset() });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Export data -->
        <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
            <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Export your data</p>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Download a JSON archive of all your profile data, achievements and connected accounts.</p>
            </div>
            <form class="p-6 flex flex-col gap-4" @submit.prevent="submitExport">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Confirm password</label>
                    <input v-model="exportForm.password" type="password" placeholder="Your current password"
                        class="w-full rounded-xl border px-4 py-2.5 text-[14px] transition"
                        :class="dark ? 'border-zinc-800 bg-zinc-900/60 text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50' : 'border-zinc-200 bg-white text-zinc-900 focus:outline-none focus:border-blue-400'" />
                    <p v-if="exportForm.errors.password" class="text-red-400 text-[12px] font-semibold">{{ exportForm.errors.password }}</p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="exportForm.processing"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border text-[13px] font-bold transition disabled:opacity-60"
                        :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600' : 'border-zinc-200 text-zinc-700 hover:border-zinc-300'">
                        <Download :size="13" :stroke-width="2" /> Export data
                    </button>
                </div>
            </form>
        </div>

        <!-- Delete account -->
        <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-red-500/20 bg-[#111113]' : 'border-red-200 bg-white shadow-sm'">
            <div class="px-6 py-4 border-b flex items-center gap-3" :class="dark ? 'border-red-500/10 bg-red-500/5' : 'border-red-100 bg-red-50'">
                <AlertTriangle :size="16" :stroke-width="1.8" class="text-red-400 shrink-0" />
                <div>
                    <p class="text-[14px] font-black text-red-400">Delete account</p>
                    <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">This action is permanent. Your profile will be anonymized immediately.</p>
                </div>
            </div>
            <div class="p-6">
                <button v-if="!showDeleteConfirm" type="button"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border text-[13px] font-bold transition border-red-500/30 text-red-400 hover:bg-red-500/10"
                    @click="showDeleteConfirm = true">
                    <Trash2 :size="13" :stroke-width="2" /> Delete my account
                </button>

                <form v-else class="flex flex-col gap-4" @submit.prevent="submitDelete">
                    <div class="p-4 rounded-xl border" :class="dark ? 'border-red-500/20 bg-red-500/5' : 'border-red-200 bg-red-50'">
                        <p class="text-[13px] font-semibold text-red-400 mb-1">Are you absolutely sure?</p>
                        <p class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            Your username, messages, and profile info will be permanently deleted. This cannot be undone.
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                            Type <strong class="text-red-400 font-mono">{{ username }}</strong> to confirm
                        </label>
                        <input v-model="deleteForm.username_confirm" type="text" :placeholder="username"
                            class="w-full rounded-xl border px-4 py-2.5 text-[14px] font-mono transition"
                            :class="dark ? 'border-red-500/30 bg-zinc-900/60 text-zinc-100 placeholder:text-zinc-700 focus:outline-none focus:border-red-500/50' : 'border-red-200 bg-white text-zinc-900 focus:outline-none focus:border-red-400'" />
                        <p v-if="deleteForm.errors.username_confirm" class="text-red-400 text-[12px] font-semibold">{{ deleteForm.errors.username_confirm }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Confirm password</label>
                        <input v-model="deleteForm.password" type="password"
                            class="w-full rounded-xl border px-4 py-2.5 text-[14px] transition"
                            :class="dark ? 'border-red-500/30 bg-zinc-900/60 text-zinc-100 placeholder:text-zinc-700 focus:outline-none focus:border-red-500/50' : 'border-red-200 bg-white text-zinc-900 focus:outline-none focus:border-red-400'" />
                        <p v-if="deleteForm.errors.password" class="text-red-400 text-[12px] font-semibold">{{ deleteForm.errors.password }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" class="text-[13px] font-semibold transition" :class="dark ? 'text-zinc-500 hover:text-zinc-300' : 'text-zinc-400 hover:text-zinc-600'"
                            @click="showDeleteConfirm = false">Cancel</button>
                        <button type="submit" :disabled="deleteForm.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-[13px] font-bold transition disabled:opacity-60">
                            <Trash2 :size="13" :stroke-width="2" /> Delete my account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
