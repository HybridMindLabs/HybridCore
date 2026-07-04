<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

const props = defineProps<{
    account: { timezone: string | null; locale: string | null };
}>();

const { t, supportedLocales } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const form = useForm({
    locale:   props.account.locale ?? '',
    timezone: props.account.timezone ?? '',
});

const input = computed(() => dark.value
    ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
    : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition'
);
const label = computed(() => dark.value
    ? 'text-zinc-500 text-[11px] font-bold uppercase tracking-widest'
    : 'text-zinc-400 text-[11px] font-bold uppercase tracking-widest'
);

function submit() {
    form.put(route('account.preferences.update'));
}
</script>

<template>
    <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
        <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <p class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('account.preferences') }}</p>
            <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('account.preferences_hint') }}</p>
        </div>
        <form class="p-6 flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="flex flex-col gap-2">
                    <label :class="label">{{ t('account.language') }}</label>
                    <select v-model="form.locale" :class="input">
                        <option value="">—</option>
                        <option v-for="loc in supportedLocales" :key="loc.code" :value="loc.code">{{ loc.native_name }}</option>
                    </select>
                    <p v-if="form.errors.locale" class="text-red-400 text-[12px] font-semibold">{{ form.errors.locale }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label :class="label">{{ t('account.timezone') }}</label>
                    <input v-model="form.timezone" type="text" placeholder="Europe/Sofia" :class="input" />
                    <p v-if="form.errors.timezone" class="text-red-400 text-[12px] font-semibold">{{ form.errors.timezone }}</p>
                </div>
            </div>
            <div class="flex justify-end pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                <button type="submit" :disabled="form.processing" class="bg-blue-500 hover:bg-blue-600 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl transition disabled:opacity-60 shadow-md shadow-blue-500/20">
                    {{ t('account.save_preferences') }}
                </button>
            </div>
        </form>
    </div>
</template>
