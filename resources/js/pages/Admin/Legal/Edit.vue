<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, Scale, ArrowLeft } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import MarkdownEditor from '@/components/Admin/MarkdownEditor.vue';
import PageReference from '@/components/Admin/PageReference.vue';

interface LegalPage {
    id: number;
    slug: string;
    title: string;
    subtitle: string | null;
    content: string | null;
    content_updated_at: string | null;
    is_system: boolean;
    sort_order: number;
}

const props = defineProps<{
    page: LegalPage | null;
    seoDescription: string;
}>();

const isNew = props.page === null;

const form = useForm({
    slug:               props.page?.slug ?? '',
    title:              props.page?.title ?? '',
    subtitle:           props.page?.subtitle ?? props.seoDescription,
    content:            props.page?.content ?? '',
    content_updated_at: props.page?.content_updated_at ?? new Date().toISOString().slice(0, 10),
    sort_order:         props.page?.sort_order ?? 0,
});

function submit() {
    if (isNew) {
        form.post(route('admin.legal.store'));
    } else {
        form.put(route('admin.legal.update', props.page!.slug));
    }
}

const inputClass = 'w-full bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder-[#475569] focus:outline-none focus:border-blue-500/50';
</script>

<template>
    <Head :title="isNew ? 'New Legal Page' : `Edit — ${page!.title}`" />
    <AdminLayout :title="isNew ? 'New Legal Page' : `Edit — ${page!.title}`">

        <!-- Top bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2 text-sm text-zinc-600">
                <Link :href="route('admin.legal.index')" class="hover:text-blue-400 transition-colors flex items-center gap-1">
                    <ArrowLeft :size="13" :stroke-width="1.75" />
                    Legal Pages
                </Link>
                <span>/</span>
                <span class="text-zinc-400">{{ isNew ? 'New page' : page!.title }}</span>
            </div>
            <div class="flex items-center gap-2">
                <a
                    v-if="!isNew"
                    :href="`/legal/${page!.slug}`"
                    target="_blank"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs text-zinc-600 hover:text-zinc-400 border border-zinc-800/70 transition-colors"
                >
                    <ExternalLink :size="11" :stroke-width="1.75" />
                    Preview
                </a>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-[#0a0f1a] hover:bg-blue-600 transition-colors disabled:opacity-50"
                    @click="submit"
                >
                    {{ isNew ? 'Create page' : 'Save changes' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">

            <!-- Editor (3/4) -->
            <div class="xl:col-span-3 flex flex-col gap-4">

                <!-- Title + subtitle -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">Page title <span class="text-red-400">*</span></label>
                        <input v-model="form.title" type="text" :class="inputClass" placeholder="Terms of Service" />
                        <p v-if="form.errors.title" class="text-xs text-red-400">{{ form.errors.title }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">
                            Subtitle
                            <span class="text-zinc-600 font-normal">(shown under the hero title — defaults to your site's SEO description)</span>
                        </label>
                        <input
                            v-model="form.subtitle"
                            type="text"
                            :class="inputClass"
                            :placeholder="seoDescription || 'Short description...'"
                        />
                    </div>
                </div>

                <!-- Markdown editor -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <MarkdownEditor v-model="form.content" :min-height="600" />
                    <p v-if="form.errors.content" class="px-5 pb-3 text-xs text-red-400">{{ form.errors.content }}</p>
                </div>

            </div>

            <!-- Sidebar (1/4) -->
            <div class="flex flex-col gap-4">

                <!-- Meta -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Page info</h3>

                    <!-- Slug — only for new pages -->
                    <div v-if="isNew" class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">URL slug <span class="text-red-400">*</span></label>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-zinc-600 font-mono">/legal/</span>
                            <input
                                v-model="form.slug"
                                type="text"
                                :class="[inputClass, 'font-mono']"
                                placeholder="my-page"
                            />
                        </div>
                        <p v-if="form.errors.slug" class="text-xs text-red-400">{{ form.errors.slug }}</p>
                        <p class="text-zinc-600 text-xs">Lowercase letters, numbers and hyphens only.</p>
                    </div>
                    <div v-else class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">Public URL</label>
                        <a
                            :href="`/legal/${page!.slug}`"
                            target="_blank"
                            class="text-xs text-blue-400 hover:underline font-mono"
                        >/legal/{{ page!.slug }}</a>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">Last updated date</label>
                        <input v-model="form.content_updated_at" type="date" :class="inputClass" />
                        <p class="text-zinc-600 text-xs">Shown as "Last updated" on the public page.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">Sort order</label>
                        <input v-model.number="form.sort_order" type="number" min="0" max="9999" :class="[inputClass, 'w-24']" />
                    </div>
                </div>

                <!-- Other pages quick nav -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                            <Scale :size="12" :stroke-width="1.75" />
                            Legal pages
                        </h3>
                        <Link
                            :href="route('admin.legal.create')"
                            class="text-xs text-blue-400 hover:underline"
                        >+ New</Link>
                    </div>
                    <Link
                        :href="route('admin.legal.index')"
                        class="text-xs text-zinc-500 hover:text-zinc-300 transition-colors"
                    >← All pages</Link>
                </div>

                <!-- Markdown reference -->
                <PageReference format="markdown" />

            </div>
        </div>

    </AdminLayout>
</template>
