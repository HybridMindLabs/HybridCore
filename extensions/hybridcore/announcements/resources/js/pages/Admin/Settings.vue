<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Settings } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

interface AnnouncementSettings {
    max_shown: number;
    show_on_home: boolean;
}

const props = defineProps<{ settings: AnnouncementSettings }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const form = useForm({ ...props.settings });

const inputClass = computed(() =>
    dark.value
        ? 'bg-zinc-900 border-zinc-800 text-zinc-200 focus:border-blue-500'
        : 'bg-white border-zinc-200 text-zinc-900 focus:border-blue-400'
);
const labelClass = computed(() => dark.value ? 'text-zinc-400' : 'text-zinc-600');
</script>

<template>
    <Head title="Announcement Settings" />
    <AdminLayout title="Announcement Settings">
        <PageHeader title="Announcement Settings" :icon="Settings" />

        <form class="max-w-xl" @submit.prevent="form.patch(route('admin.settings.extensions.announcements.update'))">
            <div class="rounded-xl border p-6 flex flex-col gap-5"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">

                <!-- max_shown -->
                <div>
                    <label class="block text-[12px] font-bold mb-1.5" :class="labelClass">
                        Maximum announcements shown on home page
                    </label>
                    <input v-model.number="form.max_shown" type="number" min="1" max="10"
                        class="w-32 rounded-lg border px-3 py-2 text-[13px] outline-none transition focus:ring-2 focus:ring-blue-500/20"
                        :class="inputClass" />
                </div>

                <!-- show_on_home -->
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <div class="relative">
                        <input v-model="form.show_on_home" type="checkbox" class="sr-only peer" />
                        <div class="w-10 h-5 rounded-full transition peer-checked:bg-blue-500"
                            :class="dark ? 'bg-zinc-700' : 'bg-zinc-200'" />
                        <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5" />
                    </div>
                    <span class="text-[13px] font-semibold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                        Show widget on home page
                    </span>
                </label>
            </div>

            <div class="mt-4">
                <button type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 disabled:opacity-60 text-white text-[13px] font-bold px-5 py-2.5 rounded-xl transition">
                    Save settings
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
