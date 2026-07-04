<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, BookOpen, ArrowLeft, Lock } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import MarkdownEditor from '@/components/Admin/MarkdownEditor.vue';
import PageReference from '@/components/Admin/PageReference.vue';
import { watch } from 'vue';

interface Rule {
    id: number;
    slug: string;
    title: string;
    excerpt: string | null;
    content: string | null;
    is_system: boolean;
    published: boolean;
    sort_order: number;
}

const props = defineProps<{ rule: Rule | null }>();

const isNew = props.rule === null;

const form = useForm({
    title:      props.rule?.title ?? '',
    excerpt:    props.rule?.excerpt ?? '',
    content:    props.rule?.content ?? '',
    published:  props.rule?.published ?? true,
    sort_order: props.rule?.sort_order ?? 0,
});

// Live slug preview for new rules
const slugPreview = (() => {
    if (!isNew) return null;
    const r = { value: '' };
    watch(() => form.title, (t) => {
        r.value = t.toLowerCase()
            .replace(/[^а-яa-z0-9\s-]/gi, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    });
    return r;
})();

function submit() {
    if (isNew) {
        form.post(route('admin.rules.store'));
    } else {
        form.put(route('admin.rules.update', props.rule!.slug));
    }
}

const inputClass = 'w-full bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder-[#475569] focus:outline-none focus:border-blue-500/50';
</script>

<template>
    <Head :title="isNew ? 'New Rule' : `Edit — ${rule!.title}`" />
    <AdminLayout :title="isNew ? 'New Rule' : `Edit — ${rule!.title}`">

        <!-- Top bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2 text-sm text-zinc-600">
                <Link :href="route('admin.rules.index')" class="hover:text-blue-400 transition-colors flex items-center gap-1">
                    <ArrowLeft :size="13" :stroke-width="1.75" />
                    Rules
                </Link>
                <span>/</span>
                <span class="text-zinc-400">{{ isNew ? 'New rule' : rule!.title }}</span>
            </div>
            <div class="flex items-center gap-2">
                <a
                    v-if="!isNew"
                    :href="`/rules/${rule!.slug}`"
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
                    {{ isNew ? 'Create rule' : 'Save changes' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">

            <!-- Editor (3/4) -->
            <div class="xl:col-span-3 flex flex-col gap-4">

                <!-- Title + excerpt -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">
                            Rule title <span class="text-red-400">*</span>
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            :class="inputClass"
                            placeholder="Server Administrator Rules"
                            :disabled="rule?.is_system && !isNew"
                        />
                        <p v-if="form.errors.title" class="text-xs text-red-400">{{ form.errors.title }}</p>
                        <!-- Slug preview for new -->
                        <p v-if="isNew && slugPreview?.value" class="text-xs text-zinc-600">
                            URL: <span class="font-mono text-zinc-500">/rules/{{ slugPreview.value }}</span>
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">
                            Short description
                            <span class="text-zinc-600 font-normal">(shown in the sidebar and as SEO meta description)</span>
                        </label>
                        <input
                            v-model="form.excerpt"
                            type="text"
                            :class="inputClass"
                            placeholder="Rules that apply to all server administrators."
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
                    <h3 class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Rule info</h3>

                    <!-- System badge -->
                    <div v-if="rule?.is_system" class="flex items-center gap-2 text-xs text-zinc-500 bg-zinc-800/50 rounded-lg px-3 py-2">
                        <Lock :size="12" :stroke-width="1.75" />
                        System rule — cannot be deleted or renamed.
                    </div>

                    <!-- Public URL -->
                    <div v-if="!isNew" class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">Public URL</label>
                        <a
                            :href="`/rules/${rule!.slug}`"
                            target="_blank"
                            class="text-xs text-blue-400 hover:underline font-mono"
                        >/rules/{{ rule!.slug }}</a>
                    </div>

                    <!-- Published toggle -->
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-zinc-400">Published</p>
                            <p class="text-xs text-zinc-600">Visible to the public</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-9 h-5 rounded-full transition-colors focus:outline-none"
                            :class="form.published ? 'bg-blue-500' : 'bg-zinc-700'"
                            @click="form.published = !form.published"
                        >
                            <span
                                class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                :class="form.published ? 'translate-x-4' : 'translate-x-0'"
                            />
                        </button>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-zinc-400">Sort order</label>
                        <input v-model.number="form.sort_order" type="number" min="0" max="9999" :class="[inputClass, 'w-24']" />
                        <p class="text-zinc-600 text-xs">Lower = appears first in the sidebar.</p>
                    </div>
                </div>

                <!-- Quick nav -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-semibold text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                            <BookOpen :size="12" :stroke-width="1.75" />
                            Rules
                        </h3>
                        <Link :href="route('admin.rules.create')" class="text-xs text-blue-400 hover:underline">+ New</Link>
                    </div>
                    <Link
                        :href="route('admin.rules.index')"
                        class="text-xs text-zinc-500 hover:text-zinc-300 transition-colors"
                    >← All rules</Link>
                </div>

                <PageReference format="markdown" />
            </div>
        </div>

    </AdminLayout>
</template>
