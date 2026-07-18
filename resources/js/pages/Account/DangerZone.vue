<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, Check, Download, Info, Trash2, X } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';

const props = defineProps<{
    username: string;
    hasPassword: boolean;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const showDeleteConfirm = ref(false);

const deleteForm = useForm({ username_confirm: '', password: '' });
const exportForm = useForm({ password: '', username_confirm: '' });

/**
 * A provider-only account has a random password its owner never saw, so both
 * flows confirm with the username instead. Demanding the password made data
 * export and deletion impossible for those accounts.
 */
const confirmsWithPassword = computed(() => props.hasPassword);

const canDelete = computed(
    () => deleteForm.username_confirm.length > 0 && (!confirmsWithPassword.value || deleteForm.password.length > 0),
);
const canExport = computed(() =>
    confirmsWithPassword.value ? exportForm.password.length > 0 : exportForm.username_confirm.length > 0,
);

function submitDelete() {
    deleteForm.post(route('account.delete'));
}

function submitExport() {
    exportForm.post(route('account.export'), { onSuccess: () => exportForm.reset() });
}

const input = computed(() =>
    dark.value
        ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
        : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] placeholder:text-zinc-400 focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition',
);
const dangerInput = computed(() =>
    dark.value
        ? 'w-full rounded-xl border border-red-500/30 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-red-500/60 focus:ring-2 focus:ring-red-500/10 transition'
        : 'w-full rounded-xl border border-red-300 bg-white text-zinc-900 px-4 py-2.5 text-[14px] placeholder:text-zinc-400 focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/10 transition',
);
const label = computed(() =>
    dark.value
        ? 'text-zinc-400 text-[11px] font-bold uppercase tracking-widest'
        : 'text-zinc-500 text-[11px] font-bold uppercase tracking-widest',
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Export -->
        <div
            class="rounded-2xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
        >
            <div
                class="px-6 py-4 border-b flex items-start gap-2.5"
                :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'"
            >
                <Download :size="15" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-blue-400' : 'text-blue-600'" />
                <div>
                    <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('account.dz_export_title') }}
                    </h2>
                    <p class="text-[12px] mt-0.5 leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ t('account.dz_export_subtitle') }}
                    </p>
                </div>
            </div>

            <form class="p-6 flex flex-col gap-4" @submit.prevent="submitExport">
                <div v-if="confirmsWithPassword" class="flex flex-col gap-2">
                    <label for="export_password" :class="label">{{ t('account.dz_confirm_password') }}</label>
                    <input
                        id="export_password"
                        v-model="exportForm.password"
                        type="password"
                        autocomplete="current-password"
                        :placeholder="t('account.dz_confirm_password_placeholder')"
                        :aria-invalid="!!exportForm.errors.password"
                        :class="[input, exportForm.errors.password ? '!border-red-500/60' : '']"
                    />
                    <p v-if="exportForm.errors.password" class="text-red-500 text-[12px] font-semibold">
                        {{ exportForm.errors.password }}
                    </p>
                </div>

                <div v-else class="flex flex-col gap-2">
                    <label for="export_username" :class="label">
                        {{ t('account.dz_confirm_username', { username }) }}
                    </label>
                    <input
                        id="export_username"
                        v-model="exportForm.username_confirm"
                        type="text"
                        :placeholder="username"
                        :aria-invalid="!!exportForm.errors.username_confirm"
                        :class="[input, 'font-mono', exportForm.errors.username_confirm ? '!border-red-500/60' : '']"
                    />
                    <p v-if="exportForm.errors.username_confirm" class="text-red-500 text-[12px] font-semibold">
                        {{ exportForm.errors.username_confirm }}
                    </p>
                    <p class="text-[12px] leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ t('account.dz_no_password_note') }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Transition
                        enter-active-class="transition duration-200"
                        enter-from-class="opacity-0 translate-y-1"
                        leave-active-class="transition duration-200"
                        leave-to-class="opacity-0"
                    >
                        <span
                            v-if="exportForm.recentlySuccessful"
                            role="status"
                            class="inline-flex items-center gap-1 text-[12px] font-semibold"
                            :class="dark ? 'text-emerald-400' : 'text-emerald-600'"
                        >
                            <Check :size="13" :stroke-width="2.4" />
                            {{ t('account.export_queued') }}
                        </span>
                    </Transition>
                    <button
                        type="submit"
                        :disabled="exportForm.processing || !canExport"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border text-[13px] font-bold transition
                               disabled:opacity-60 disabled:cursor-not-allowed
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                        :class="dark
                            ? 'border-zinc-800 text-zinc-200 hover:text-white hover:border-zinc-600'
                            : 'border-zinc-300 text-zinc-800 hover:border-zinc-400 hover:bg-zinc-50'"
                    >
                        <Download :size="13" :stroke-width="2" />
                        {{ t('account.dz_export_button') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Delete -->
        <div
            class="rounded-2xl border overflow-hidden"
            :class="dark ? 'border-red-500/20 bg-[#111113]' : 'border-red-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
        >
            <div
                class="px-6 py-4 border-b flex items-start gap-2.5"
                :class="dark ? 'border-red-500/10 bg-red-500/5' : 'border-red-100 bg-red-50'"
            >
                <AlertTriangle :size="15" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-red-400' : 'text-red-600'" />
                <div>
                    <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-red-400' : 'text-red-700'">
                        {{ t('account.dz_delete_title') }}
                    </h2>
                    <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                        {{ t('account.dz_delete_subtitle') }}
                    </p>
                </div>
            </div>

            <div class="p-6">
                <button
                    v-if="!showDeleteConfirm"
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border text-[13px] font-bold transition
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                    :class="dark
                        ? 'border-red-500/30 text-red-400 hover:bg-red-500/10'
                        : 'border-red-300 text-red-700 hover:bg-red-50'"
                    @click="showDeleteConfirm = true"
                >
                    <Trash2 :size="13" :stroke-width="2" />
                    {{ t('account.dz_delete_open') }}
                </button>

                <form v-else class="flex flex-col gap-4" @submit.prevent="submitDelete">
                    <p class="text-[14px] font-black" :class="dark ? 'text-red-400' : 'text-red-700'">
                        {{ t('account.dz_delete_sure') }}
                    </p>

                    <!-- Spells out both sides. The old copy claimed messages
                         were deleted, which the code never did. -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div
                            class="rounded-xl border p-4"
                            :class="dark ? 'border-red-500/20 bg-red-500/5' : 'border-red-200 bg-red-50'"
                        >
                            <p class="text-[12px] font-bold mb-2 inline-flex items-center gap-1.5" :class="dark ? 'text-red-400' : 'text-red-700'">
                                <X :size="12" :stroke-width="2.4" />
                                {{ t('account.dz_delete_removed_title') }}
                            </p>
                            <ul class="flex flex-col gap-1.5 text-[12px] leading-relaxed" :class="dark ? 'text-zinc-400' : 'text-zinc-700'">
                                <li>{{ t('account.dz_delete_removed_1') }}</li>
                                <li>{{ t('account.dz_delete_removed_2') }}</li>
                                <li>{{ t('account.dz_delete_removed_3') }}</li>
                            </ul>
                        </div>
                        <div
                            class="rounded-xl border p-4"
                            :class="dark ? 'border-zinc-800/60 bg-zinc-900/40' : 'border-zinc-200 bg-zinc-50'"
                        >
                            <p class="text-[12px] font-bold mb-2 inline-flex items-center gap-1.5" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                                <Info :size="12" :stroke-width="2.4" />
                                {{ t('account.dz_delete_kept_title') }}
                            </p>
                            <ul class="flex flex-col gap-1.5 text-[12px] leading-relaxed" :class="dark ? 'text-zinc-400' : 'text-zinc-700'">
                                <li>{{ t('account.dz_delete_kept_1') }}</li>
                                <li>{{ t('account.dz_delete_kept_2') }}</li>
                            </ul>
                        </div>
                    </div>

                    <p
                        class="text-[12px] rounded-xl border px-4 py-3 leading-relaxed"
                        :class="dark ? 'border-amber-500/20 bg-amber-500/10 text-amber-200' : 'border-amber-300 bg-amber-50 text-amber-900'"
                    >
                        {{ t('account.dz_delete_export_first') }}
                    </p>

                    <div class="flex flex-col gap-2">
                        <label for="delete_username" :class="label">
                            {{ t('account.dz_confirm_username', { username }) }}
                        </label>
                        <input
                            id="delete_username"
                            v-model="deleteForm.username_confirm"
                            type="text"
                            :placeholder="username"
                            :aria-invalid="!!deleteForm.errors.username_confirm"
                            :class="[dangerInput, 'font-mono']"
                        />
                        <p v-if="deleteForm.errors.username_confirm" class="text-red-500 text-[12px] font-semibold">
                            {{ deleteForm.errors.username_confirm }}
                        </p>
                    </div>

                    <div v-if="confirmsWithPassword" class="flex flex-col gap-2">
                        <label for="delete_password" :class="label">{{ t('account.dz_confirm_password') }}</label>
                        <input
                            id="delete_password"
                            v-model="deleteForm.password"
                            type="password"
                            autocomplete="current-password"
                            :aria-invalid="!!deleteForm.errors.password"
                            :class="dangerInput"
                        />
                        <p v-if="deleteForm.errors.password" class="text-red-500 text-[12px] font-semibold">
                            {{ deleteForm.errors.password }}
                        </p>
                    </div>
                    <p v-else class="text-[12px] leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ t('account.dz_no_password_note') }}
                    </p>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="text-[13px] font-semibold transition"
                            :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'"
                            @click="showDeleteConfirm = false"
                        >
                            {{ t('account.dz_cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="deleteForm.processing || !canDelete"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white
                                   text-[13px] font-bold transition disabled:opacity-60 disabled:cursor-not-allowed
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                        >
                            <Trash2 :size="13" :stroke-width="2" />
                            {{ t('account.dz_delete_open') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
