<script setup lang="ts">
import { InertiaForm } from '@inertiajs/vue3';
import MarkdownEditor from '@/components/Admin/MarkdownEditor.vue';

export interface PageFormData {
    title: string;
    slug: string;
    body: string;
    status: string;
    seo_title: string;
    seo_description: string;
    seo_og_image: string;
    [key: string]: string;
}

defineProps<{ form: InertiaForm<PageFormData>; submitLabel: string }>();
defineEmits<{ submit: [] }>();

const inputClass = 'w-full bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder-[#475569] focus:outline-none focus:border-blue-500/50';
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="('submit')">

        <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Title <span class="text-red-400">*</span></label>
            <input v-model="form.title" type="text" :class="inputClass" placeholder="About Us" />
            <p v-if="form.errors.title" class="mt-1 text-xs text-red-400">{{ form.errors.title }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1.5">Slug <span class="text-zinc-600">(auto if empty)</span></label>
                <input v-model="form.slug" type="text" :class="[inputClass, 'font-mono']" placeholder="about-us" />
                <p v-if="form.errors.slug" class="mt-1 text-xs text-red-400">{{ form.errors.slug }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1.5">Status</label>
                <select v-model="form.status" :class="inputClass">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-zinc-400 mb-1.5">Content <span class="text-zinc-600">(Markdown)</span></label>
            <MarkdownEditor v-model="form.body" />
            <p v-if="form.errors.body" class="mt-1 text-xs text-red-400">{{ form.errors.body }}</p>
        </div>

        <!-- SEO -->
        <div class="border-t border-zinc-800/70 pt-5">
            <h3 class="text-zinc-100 text-sm font-semibold mb-4">SEO</h3>
            <div class="flex flex-col gap-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">SEO Title <span class="text-zinc-600">(defaults to page title)</span></label>
                    <input v-model="form.seo_title" type="text" :class="inputClass" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Meta Description</label>
                    <textarea v-model="form.seo_description" rows="2" :class="[inputClass, 'resize-none']" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">OG Image URL</label>
                    <input v-model="form.seo_og_image" type="text" :class="inputClass" placeholder="https://..." />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2 border-t border-zinc-800/70">
            <slot name="cancel" />
            <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-[#0a0f1a] hover:bg-blue-600 transition-colors disabled:opacity-50"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
