<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { computed } from 'vue';

const props = defineProps<{
    preferences: Record<string, boolean> | string[];
}>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const categories = [
    { key: 'email_messages',      label: 'Direct Messages',      desc: 'Get an email when you receive a new private message.' },
    { key: 'email_notifications', label: 'System Notifications', desc: 'Important account alerts and system messages.' },
    { key: 'email_digest',        label: 'Activity Digest',      desc: 'Periodic summary of activity on the community.' },
    { key: 'email_announcements', label: 'Announcements',        desc: 'News and important announcements from the team.' },
];

const prefs = computed(() => {
    if (Array.isArray(props.preferences)) {
        return Object.fromEntries(categories.map(c => [c.key, props.preferences.includes(c.key)]));
    }
    return props.preferences ?? {};
});

const form = useForm(Object.fromEntries(
    categories.map(c => [c.key, prefs.value[c.key] !== false]),
));

function submit() {
    form.put(route('account.email-preferences.update'), { preserveScroll: true });
}
</script>

<template>
    <div class="rounded-2xl border overflow-hidden"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
        <div class="px-6 py-4 border-b flex items-center gap-2"
            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <MailCheck :size="15" :stroke-width="1.8" :class="dark ? 'text-cyan-400' : 'text-cyan-500'" />
            <div>
                <p class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Email Preferences</p>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Choose which emails you'd like to receive.</p>
            </div>
        </div>

        <form class="p-6 flex flex-col gap-4" @submit.prevent="submit">
            <div v-for="cat in categories" :key="cat.key"
                class="flex items-start justify-between gap-4 py-3 border-b last:border-b-0"
                :class="dark ? 'border-zinc-800/40' : 'border-zinc-100'">
                <div>
                    <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ cat.label }}</p>
                    <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ cat.desc }}</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                    <input type="checkbox" v-model="(form as any)[cat.key]" class="sr-only peer" />
                    <div class="w-10 h-5 rounded-full transition-colors peer-focus:outline-none"
                        :class="(form as any)[cat.key]
                            ? 'bg-cyan-500'
                            : dark ? 'bg-zinc-700' : 'bg-zinc-300'">
                        <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform"
                            :class="(form as any)[cat.key] ? 'translate-x-5' : 'translate-x-0'" />
                    </div>
                </label>
            </div>

            <div class="flex items-center justify-between pt-2">
                <button type="submit" :disabled="form.processing"
                    class="bg-cyan-500 hover:bg-cyan-600 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl transition disabled:opacity-60">
                    Save Preferences
                </button>
                <span v-if="form.recentlySuccessful" class="text-emerald-400 text-[12px] font-semibold">Saved!</span>
            </div>
        </form>
    </div>
</template>
