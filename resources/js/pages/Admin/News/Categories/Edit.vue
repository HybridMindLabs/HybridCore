<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { watch } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import IconPicker from '@/components/Admin/IconPicker.vue';

interface Category {
    id: number; name: string; slug: string; description: string | null;
    color: string; icon: string; meta_title: string | null; meta_description: string | null; sort_order: number;
}
const props = defineProps<{ category: Category }>();

function slugify(str: string): string {
    return str.toLowerCase().trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

const form = useForm({
    name: props.category.name,
    slug: props.category.slug,
    description: props.category.description ?? '',
    color: props.category.color,
    icon: props.category.icon,
    meta_title: props.category.meta_title ?? '',
    meta_description: props.category.meta_description ?? '',
    sort_order: props.category.sort_order,
});

// Keep slug in sync only when user hasn't manually changed it
let slugEdited = false;
watch(() => form.name, (v) => { if (!slugEdited) form.slug = slugify(v); });

const ic = 'w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-xl px-3.5 py-2.5 text-[13px] focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 placeholder:text-zinc-700';
const lc = 'text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5 block';
</script>

<template>
    <Head :title="`Edit: ${category.name}`" />
    <AdminLayout :title="`Edit: ${category.name}`">
        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.news.categories.index')" class="flex items-center gap-1.5 text-[13px] text-zinc-500 hover:text-zinc-200 transition">
                <ChevronLeft :size="14" :stroke-width="2" /> Categories
            </Link>
        </div>

        <div class="max-w-2xl">
            <div class="rounded-2xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
                <div class="border-b border-zinc-800/60 bg-[#1a1a1e] px-5 py-3.5">
                    <h2 class="text-[14px] font-bold text-zinc-100">Edit Category</h2>
                </div>
                <form class="p-5 flex flex-col gap-4" @submit.prevent="form.put(route('admin.news.categories.update', category.id))">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label :class="lc">Name</label>
                            <input v-model="form.name" :class="[ic, form.errors.name ? 'border-red-500/50' : '']" />
                            <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label :class="lc">Slug</label>
                            <input v-model="form.slug" :class="[ic, form.errors.slug ? 'border-red-500/50' : '']"
                                @input="slugEdited = true" />
                            <p v-if="form.errors.slug" class="text-red-400 text-xs mt-1">{{ form.errors.slug }}</p>
                        </div>
                    </div>

                    <div>
                        <label :class="lc">Description</label>
                        <textarea v-model="form.description" :class="ic" rows="2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label :class="lc">Icon</label>
                            <IconPicker v-model="form.icon" :color="form.color" />
                        </div>
                        <div>
                            <label :class="lc">Color</label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.color" type="color"
                                    class="w-10 h-10 rounded-xl border border-zinc-800/70 bg-zinc-900/60 cursor-pointer p-1 shrink-0" />
                                <input v-model="form.color" :class="ic" placeholder="#3b82f6" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label :class="lc">Sort Order</label>
                        <input v-model.number="form.sort_order" type="number" min="0" :class="ic" style="max-width:120px" />
                    </div>

                    <div class="flex justify-end gap-2 pt-1 border-t border-zinc-800/50">
                        <Link :href="route('admin.news.categories.index')"
                            class="px-4 py-2 rounded-xl border border-zinc-800/70 text-zinc-400 hover:text-zinc-100 text-[13px] font-semibold transition">
                            Cancel
                        </Link>
                        <button type="submit" :disabled="form.processing"
                            class="px-5 py-2 bg-blue-500 hover:bg-blue-600 text-white text-[13px] font-bold rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-60">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>