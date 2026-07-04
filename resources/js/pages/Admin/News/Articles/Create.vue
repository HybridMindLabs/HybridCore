<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { defineAsyncComponent } from 'vue';
const NewsArticleForm = defineAsyncComponent(() => import('./Form.vue'));

interface Category { id: number; name: string; color: string }
interface Tag { id: number; name: string; slug: string }
const props = defineProps<{ categories: Category[]; tags: Tag[] }>();

const form = useForm({
    title: '', slug: '', category_id: null as number | null,
    excerpt: '', body: '', format: 'markdown',
    featured_image: '', status: 'draft',
    is_pinned: false, is_featured: false,
    published_at: '', meta_title: '', meta_description: '', og_image: '',
    tags: [] as string[],
});
</script>

<template>
    <Head title="New Article" />
    <AdminLayout title="New Article">
        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.news.articles.index')" class="flex items-center gap-1.5 text-[13px] text-zinc-500 hover:text-zinc-200 transition">
                <ChevronLeft :size="14" :stroke-width="2" /> Articles
            </Link>
        </div>
        <NewsArticleForm :form="form" :categories="categories" :tags="tags"
            :submit-route="route('admin.news.articles.store')" :upload-route="route('admin.news.media.upload')" method="post" submit-label="Create Article" />
    </AdminLayout>
</template>