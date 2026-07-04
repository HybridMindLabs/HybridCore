<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Tag, Newspaper } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface Category {
    id: number; name: string; slug: string; color: string; icon: string; articles_count: number;
}
defineProps<{ categories: Category[] }>();

function destroy(id: number, name: string) {
    if (!confirm(`Delete category "${name}"?`)) return;
    router.delete(route('admin.news.categories.destroy', id));
}
</script>

<template>
    <Head title="News Categories" />
    <AdminLayout title="News Categories">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-[18px] font-black text-zinc-100">News Categories</h1>
                <p class="text-[12px] text-zinc-500 mt-0.5">{{ categories.length }} categories</p>
            </div>
            <Link :href="route('admin.news.categories.create')"
                class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white font-bold text-[13px] px-4 py-2 rounded-xl transition shadow-md shadow-blue-500/20">
                <Plus :size="14" :stroke-width="2.2" /> New Category
            </Link>
        </div>

        <div class="rounded-xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
            <div v-if="!categories.length" class="flex flex-col items-center justify-center py-16 text-center">
                <Tag :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                <p class="text-[13px] text-zinc-600">No categories yet.</p>
                <Link :href="route('admin.news.categories.create')" class="text-blue-400 text-[13px] font-semibold mt-2 hover:underline">Create one</Link>
            </div>

            <div v-for="cat in categories" :key="cat.id"
                class="flex items-center gap-4 px-5 py-3.5 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]">
                <div class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: cat.color }" />
                <div class="flex-1 min-w-0">
                    <p class="text-[14px] font-semibold text-zinc-100 truncate">{{ cat.name }}</p>
                    <p class="text-[12px] text-zinc-600 font-mono">/news/category/{{ cat.slug }}</p>
                </div>
                <span class="text-[12px] text-zinc-500 shrink-0">{{ cat.articles_count }} articles</span>
                <div class="flex items-center gap-1 shrink-0">
                    <Link :href="route('admin.news.categories.edit', cat.id)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600 transition">
                        <Pencil :size="12" :stroke-width="1.8" />
                    </Link>
                    <button type="button" @click="destroy(cat.id, cat.name)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-zinc-800/70 text-zinc-500 hover:text-red-400 hover:bg-red-500/8 hover:border-red-500/20 transition">
                        <Trash2 :size="12" :stroke-width="1.8" />
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>