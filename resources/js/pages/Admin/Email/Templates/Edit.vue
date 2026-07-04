<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { FileText, Eye, Save } from '@lucide/vue';
import { ref, watch } from 'vue';

interface EmailTemplate {
    id: number;
    slug: string;
    name: string;
    subject: string;
    body_html: string;
    variables: string[] | null;
    active: boolean;
    system: boolean;
}

const props = defineProps<{ template: EmailTemplate }>();

const form = useForm({
    name: props.template.name,
    subject: props.template.subject,
    body_html: props.template.body_html,
    active: props.template.active,
});

const preview = ref<{ subject: string; body: string } | null>(null);
const previewLoading = ref(false);
const showPreview = ref(false);

const sampleVars: Record<string, string> = {
    username: 'John Doe',
    app_name: 'HybridCore',
    login_url: 'https://example.com/login',
    reason: 'Violation of community rules.',
    appeal_url: 'https://example.com/appeal',
    count: '5',
    items_html: '<p style="color:#94a3b8">• New message from user1<br>• New server added</p>',
    view_url: 'https://example.com',
    sent_at: new Date().toISOString(),
};

async function fetchPreview() {
    previewLoading.value = true;
    try {
        const res = await fetch(route('admin.email.templates.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content,
            },
            body: JSON.stringify({
                subject: form.subject,
                body_html: form.body_html,
                variables: sampleVars,
            }),
        });
        preview.value = await res.json();
    } finally {
        previewLoading.value = false;
    }
}

function togglePreview() {
    showPreview.value = !showPreview.value;
    if (showPreview.value && !preview.value) {
        fetchPreview();
    }
}

watch([() => form.subject, () => form.body_html], () => {
    if (showPreview.value) fetchPreview();
});

function save() {
    form.put(route('admin.email.templates.update', props.template.id), { preserveScroll: true });
}

const breadcrumbs = [
    { label: 'Admin', href: route('admin.dashboard') },
    { label: 'Email Settings', href: route('admin.email.settings') },
    { label: 'Templates', href: route('admin.email.templates.index') },
    { label: props.template.name },
];
</script>

<template>
    <Head :title="`Edit: ${template.name}`" />
    <AdminLayout :title="`Edit Template: ${template.name}`">
        <Breadcrumb :items="breadcrumbs" />

        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                    <FileText :size="14" :stroke-width="1.8" class="text-cyan-400" />
                </div>
                <div>
                    <h1 class="text-zinc-100 text-[15px] font-black">{{ template.name }}</h1>
                    <p class="text-zinc-600 text-[11px] font-mono">{{ template.slug }}</p>
                </div>
            </div>
            <button type="button" @click="togglePreview"
                class="flex items-center gap-1.5 bg-zinc-800 border border-zinc-700 text-zinc-300 text-sm font-semibold rounded-lg px-3 py-2 hover:bg-zinc-700 transition-colors">
                <Eye :size="13" />
                {{ showPreview ? 'Hide Preview' : 'Live Preview' }}
            </button>
        </div>

        <div :class="showPreview ? 'grid grid-cols-2 gap-4' : 'max-w-2xl'">
            <!-- Editor -->
            <form @submit.prevent="save" class="space-y-4">
                <div>
                    <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Template Name</label>
                    <input v-model="form.name" type="text" required
                        class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500" />
                </div>

                <div>
                    <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Subject</label>
                    <input v-model="form.subject" type="text" required
                        class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500" />
                    <p class="text-zinc-600 text-[11px] mt-1">Use <code class="text-cyan-500">&#123;&#123;variable&#125;&#125;</code> syntax.</p>
                </div>

                <div>
                    <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Body HTML</label>
                    <textarea v-model="form.body_html" rows="18" required spellcheck="false"
                        class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:border-cyan-500 resize-y"></textarea>
                </div>

                <div v-if="template.variables?.length" class="bg-zinc-900/50 border border-zinc-800 rounded-lg p-3">
                    <p class="text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-2">Available Variables</p>
                    <div class="flex flex-wrap gap-1.5">
                        <code v-for="v in template.variables" :key="v"
                            class="text-cyan-500 text-[11px] bg-cyan-500/10 rounded px-1.5 py-0.5">&#123;&#123;{{ v }}&#125;&#125;</code>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="form.active" class="rounded border-zinc-700 bg-zinc-900 text-cyan-500 focus:ring-cyan-500" />
                        <span class="text-zinc-400 text-sm">Active</span>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" :disabled="form.processing"
                        class="flex items-center gap-1.5 bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-sm font-semibold rounded-lg px-4 py-2 hover:bg-cyan-500/30 transition-colors disabled:opacity-50">
                        <Save :size="13" />
                        Save Template
                    </button>
                    <span v-if="form.recentlySuccessful" class="text-emerald-400 text-xs">Saved!</span>
                </div>
            </form>

            <!-- Preview pane -->
            <div v-if="showPreview" class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800 flex items-center gap-2">
                    <Eye :size="13" class="text-zinc-500" />
                    <span class="text-zinc-400 text-sm font-semibold">Preview</span>
                    <span v-if="previewLoading" class="text-zinc-600 text-xs ml-auto">Rendering…</span>
                    <span v-else-if="preview" class="text-zinc-600 text-xs ml-auto truncate max-w-[200px]">{{ preview.subject }}</span>
                </div>
                <div v-if="preview" class="overflow-auto" style="height: calc(100vh - 280px)">
                    <iframe :srcdoc="preview.body" class="w-full h-full border-0" sandbox="allow-same-origin" />
                </div>
                <div v-else class="flex items-center justify-center h-48 text-zinc-600 text-sm">
                    Loading preview…
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
