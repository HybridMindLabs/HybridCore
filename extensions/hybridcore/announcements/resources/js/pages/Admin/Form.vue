<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Megaphone, ArrowLeft } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

interface Announcement {
    id: number;
    title: string;
    body: string;
    type: string;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    sort: number;
}

const props = defineProps<{ announcement?: Announcement }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const editing = computed(() => !!props.announcement);

const form = useForm({
    title:     props.announcement?.title     ?? '',
    body:      props.announcement?.body      ?? '',
    type:      props.announcement?.type      ?? 'info',
    is_active: props.announcement?.is_active ?? true,
    starts_at: props.announcement?.starts_at ?? null,
    ends_at:   props.announcement?.ends_at   ?? null,
    sort:      props.announcement?.sort      ?? 100,
});

function submit() {
    if (editing.value) {
        form.patch(route('admin.announcements.update', props.announcement!.id));
    } else {
        form.post(route('admin.announcements.store'));
    }
}

const inputClass = computed(() =>
    dark.value
        ? 'bg-zinc-900 border-zinc-800 text-zinc-200 placeholder-zinc-600 focus:border-blue-500'
        : 'bg-white border-zinc-200 text-zinc-900 placeholder-zinc-400 focus:border-blue-400'
);
const labelClass = computed(() =>
    dark.value ? 'text-zinc-400' : 'text-zinc-600'
);
const errorClass = 'text-red-400 text-[11px] mt-1';
</script>

<template>
    <Head :title="editing ? 'Edit Announcement' : 'New Announcement'" />
    <AdminLayout :title="editing ? 'Edit Announcement' : 'New Announcement'">
        <PageHeader :title="editing ? 'Edit Announcement' : 'New Announcement'" :icon="Megaphone">
            <template #actions>
                <Link :href="route('admin.announcements.index')"
                    class="inline-flex items-center gap-2 text-[13px] font-semibold px-4 py-2 rounded-xl border transition"
                    :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-200' : 'border-zinc-200 text-zinc-600 hover:text-zinc-900'">
                    <ArrowLeft :size="13" :stroke-width="2" />
                    Back
                </Link>
            </template>
        </PageHeader>

        <form class="max-w-2xl" @submit.prevent="submit">
            <div class="rounded-xl border p-6 flex flex-col gap-5"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">

                <!-- Title -->
                <div>
                    <label class="block text-[12px] font-bold mb-1.5" :class="labelClass">Title</label>
                    <input v-model="form.title" type="text" class="w-full rounded-lg border px-3 py-2 text-[13px] outline-none transition focus:ring-2 focus:ring-blue-500/20" :class="inputClass" />
                    <p v-if="form.errors.title" :class="errorClass">{{ form.errors.title }}</p>
                </div>

                <!-- Body -->
                <div>
                    <label class="block text-[12px] font-bold mb-1.5" :class="labelClass">Message</label>
                    <textarea v-model="form.body" rows="3" class="w-full rounded-lg border px-3 py-2 text-[13px] outline-none transition resize-y focus:ring-2 focus:ring-blue-500/20" :class="inputClass" />
                    <p v-if="form.errors.body" :class="errorClass">{{ form.errors.body }}</p>
                </div>

                <!-- Type + Active -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[12px] font-bold mb-1.5" :class="labelClass">Type</label>
                        <select v-model="form.type" class="w-full rounded-lg border px-3 py-2 text-[13px] outline-none transition focus:ring-2 focus:ring-blue-500/20" :class="inputClass">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="danger">Danger</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold mb-1.5" :class="labelClass">Sort order</label>
                        <input v-model.number="form.sort" type="number" min="0" max="9999" class="w-full rounded-lg border px-3 py-2 text-[13px] outline-none transition focus:ring-2 focus:ring-blue-500/20" :class="inputClass" />
                    </div>
                </div>

                <!-- Schedule -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[12px] font-bold mb-1.5" :class="labelClass">Show from (optional)</label>
                        <input v-model="form.starts_at" type="datetime-local" class="w-full rounded-lg border px-3 py-2 text-[13px] outline-none transition focus:ring-2 focus:ring-blue-500/20" :class="inputClass" />
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold mb-1.5" :class="labelClass">Show until (optional)</label>
                        <input v-model="form.ends_at" type="datetime-local" class="w-full rounded-lg border px-3 py-2 text-[13px] outline-none transition focus:ring-2 focus:ring-blue-500/20" :class="inputClass" />
                    </div>
                </div>

                <!-- Active toggle -->
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <div class="relative">
                        <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                        <div class="w-10 h-5 rounded-full transition peer-checked:bg-blue-500"
                            :class="dark ? 'bg-zinc-700' : 'bg-zinc-200'" />
                        <div class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5" />
                    </div>
                    <span class="text-[13px] font-semibold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">Active</span>
                </label>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <button type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 disabled:opacity-60 text-white text-[13px] font-bold px-5 py-2.5 rounded-xl transition">
                    {{ editing ? 'Save changes' : 'Create announcement' }}
                </button>
                <Link :href="route('admin.announcements.index')"
                    class="text-[13px] font-semibold transition-colors"
                    :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-400 hover:text-zinc-700'">
                    Cancel
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
