<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, FileText } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import MarkdownEditor from '@/components/Admin/MarkdownEditor.vue';
import PageReference from '@/components/Admin/PageReference.vue';

const form = useForm({
    title: '',
    slug: '',
    body: '',
    format: 'markdown' as 'markdown' | 'html',
    layout: 'default',
    status: 'draft',
    seo_title: '',
    seo_description: '',
    seo_og_image: '',
});

function submit() {
    form.post(route('admin.pages.store'));
}

const inputClass = 'w-full bg-zinc-900/60 border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20';

const LAYOUTS = [
    { key: 'default', label: 'Default', desc: 'max-w-3xl' },
    { key: 'wide',    label: 'Wide',    desc: 'max-w-5xl' },
    { key: 'centered', label: 'Centered', desc: 'max-w-2xl' },
];
</script>

<template>
    <Head title="New Page" />
    <AdminLayout title="New Page">

        <!-- Top bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2 text-sm">
                <Link :href="route('admin.pages.index')" class="flex items-center gap-1.5 text-zinc-500 hover:text-zinc-100 transition-colors">
                    <ChevronLeft :size="13" :stroke-width="1.75" /> Pages
                </Link>
                <span class="text-zinc-700">/</span>
                <span class="text-zinc-300">New Page</span>
            </div>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.pages.index')" class="px-4 py-2 text-sm text-zinc-500 hover:text-zinc-100 transition-colors">
                    Cancel
                </Link>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                    @click="submit"
                >
                    {{ form.processing ? 'Creating…' : 'Create Page' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-5">

            <!-- Main: editor (3/4) -->
            <div class="xl:col-span-3 flex flex-col gap-4">

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <label class="flex items-center gap-1.5 text-xs font-medium text-zinc-400 mb-2">
                        <FileText :size="12" :stroke-width="1.75" class="text-blue-400" />
                        Title <span class="text-red-400">*</span>
                    </label>
                    <input v-model="form.title" type="text" :class="inputClass" placeholder="Page title…" />
                    <p v-if="form.errors.title" class="mt-1.5 text-xs text-red-400">{{ form.errors.title }}</p>
                </div>

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <MarkdownEditor
                        v-model="form.body"
                        :format="form.format"
                        :min-height="600"
                        @update:format="form.format = $event as 'markdown' | 'html'"
                    />
                    <p v-if="form.errors.body" class="px-5 pb-3 text-xs text-red-400">{{ form.errors.body }}</p>
                </div>

            </div>

            <!-- Sidebar -->
            <div class="flex flex-col gap-4">

                <!-- Publish settings -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-500 text-xs font-medium">Status</label>
                        <select v-model="form.status" :class="inputClass">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-500 text-xs font-medium">
                            Slug <span class="text-zinc-700 font-normal">auto if empty</span>
                        </label>
                        <input v-model="form.slug" type="text" :class="inputClass + ' font-mono text-xs'" placeholder="page-slug" />
                        <p v-if="form.errors.slug" class="text-xs text-red-400">{{ form.errors.slug }}</p>
                    </div>
                </div>

                <!-- Layout picker -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <p class="text-zinc-500 text-xs font-semibold uppercase tracking-wider mb-3">Layout</p>
                    <div class="flex flex-col gap-2">
                        <label
                            v-for="l in LAYOUTS"
                            :key="l.key"
                            class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                            :class="form.layout === l.key
                                ? 'border-blue-500/50 bg-blue-500/5'
                                : 'border-zinc-800/70 hover:border-zinc-700'"
                        >
                            <input v-model="form.layout" type="radio" :value="l.key" class="sr-only" />
                            <div class="w-12 h-8 rounded border border-zinc-800/70 bg-zinc-900 flex items-center justify-center shrink-0">
                                <div
                                    class="h-4 rounded-sm bg-blue-500/30"
                                    :class="l.key === 'default' ? 'w-8' : l.key === 'wide' ? 'w-10' : 'w-6'"
                                />
                            </div>
                            <div>
                                <p class="text-zinc-100 text-xs font-medium">{{ l.label }}</p>
                                <p class="text-zinc-600 text-[10px] font-mono">{{ l.desc }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- SEO -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <p class="text-zinc-500 text-xs font-semibold uppercase tracking-wider">SEO</p>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-500 text-xs font-medium">SEO Title</label>
                        <input v-model="form.seo_title" type="text" :class="inputClass" placeholder="Defaults to page title" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-500 text-xs font-medium">Meta Description</label>
                        <textarea v-model="form.seo_description" rows="3" :class="inputClass + ' resize-none text-xs'" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-500 text-xs font-medium">OG Image URL</label>
                        <input v-model="form.seo_og_image" type="text" :class="inputClass + ' text-xs font-mono'" placeholder="https://…" />
                    </div>
                </div>

                <PageReference :format="form.format" />

            </div>
        </div>

    </AdminLayout>
</template>
