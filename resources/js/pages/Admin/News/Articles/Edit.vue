<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { defineAsyncComponent } from 'vue';
const NewsArticleForm = defineAsyncComponent(() => import('./Form.vue'));

interface Category { id: number; name: string; color: string }
interface Tag { id: number; name: string; slug: string }
interface Article {
    id: number; title: string; slug: string; category_id: number | null;
    excerpt: string | null; body: string; format: string;
    featured_image: string | null; status: string;
    is_pinned: boolean; is_featured: boolean;
    published_at: string | null; meta_title: string | null; meta_description: string | null; og_image: string | null;
    tags: Tag[];
}
const props = defineProps<{ article: Article; categories: Category[]; tags: Tag[] }>();

const form = useForm({
    title: props.article.title,
    slug: props.article.slug,
    category_id: props.article.category_id,
    excerpt: props.article.excerpt ?? '',
    body: props.article.body,
    format: props.article.format,
    featured_image: props.article.featured_image ?? '',
    status: props.article.status,
    is_pinned: props.article.is_pinned,
    is_featured: props.article.is_featured,
    published_at: props.article.published_at ?? '',
    meta_title: props.article.meta_title ?? '',
    meta_description: props.article.meta_description ?? '',
    og_image: props.article.og_image ?? '',
    tags: props.article.tags.map(t => t.name),
});
</script>

<template>
    <Head :title="`Edit: ${article.title}`" />
    <AdminLayout :title="`Edit: ${article.title}`">
        <div class="flex items-center gap-3 mb-5">
            <Link :href="route('admin.news.articles.index')" class="flex items-center gap-1.5 text-[13px] text-zinc-500 hover:text-zinc-200 transition">
                <ChevronLeft :size="14" :stroke-width="2" /> Articles
            </Link>
        </div>
        <NewsArticleForm :form="form" :categories="categories" :tags="tags"
            :submit-route="route('admin.news.articles.update', article.id)" :upload-route="route('admin.news.media.upload')" method="put" submit-label="Save Changes" />
    </AdminLayout>
</template>