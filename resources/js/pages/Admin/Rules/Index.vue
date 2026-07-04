<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Plus, Pencil, Trash2, ExternalLink, Lock, Eye, EyeOff } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface Rule {
    id: number;
    slug: string;
    title: string;
    excerpt: string | null;
    is_system: boolean;
    published: boolean;
    sort_order: number;
    updated_at: string;
}

const props = defineProps<{ rules: Rule[] }>();

function deleteRule(slug: string) {
    if (!confirm('Delete this rule? This cannot be undone.')) return;
    router.delete(route('admin.rules.destroy', slug));
}
</script>

<template>
    <Head title="Rules" />
    <AdminLayout title="Rules">

        <PageHeader
            title="Rules"
            description="Manage community rules and guidelines visible to all members."
            :icon="BookOpen"
        >
            <template #actions>
                <Link
                    :href="route('admin.rules.create')"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-[#0a0f1a] hover:bg-blue-600 transition-colors"
                >
                    <Plus :size="14" :stroke-width="2" />
                    New rule
                </Link>
            </template>
        </PageHeader>

        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <div v-if="rules.length === 0" class="p-10 text-center">
                <BookOpen :size="24" :stroke-width="1.5" class="mx-auto mb-3 text-zinc-700" />
                <p class="text-zinc-500 text-sm">No rules yet.</p>
                <Link :href="route('admin.rules.create')" class="mt-3 inline-block text-xs text-blue-400 hover:underline">Create your first rule</Link>
            </div>

            <div v-else class="divide-y divide-zinc-800/50">
                <div
                    v-for="rule in rules"
                    :key="rule.id"
                    class="flex items-center gap-4 px-5 py-4 hover:bg-white/[0.02] transition-colors group"
                >
                    <!-- Icon -->
                    <div class="w-9 h-9 rounded-lg bg-zinc-800/60 border border-zinc-700/50 flex items-center justify-center shrink-0">
                        <BookOpen :size="15" :stroke-width="1.75" class="text-zinc-400" />
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-medium text-zinc-100">{{ rule.title }}</span>
                            <span
                                v-if="rule.is_system"
                                class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-500 flex items-center gap-1"
                            >
                                <Lock :size="9" />
                                system
                            </span>
                            <span
                                v-if="!rule.published"
                                class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-400/10 text-amber-500 border border-amber-400/20"
                            >draft</span>
                        </div>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="text-xs text-zinc-600 font-mono">/rules/{{ rule.slug }}</span>
                            <span v-if="rule.excerpt" class="text-xs text-zinc-600 truncate max-w-xs">{{ rule.excerpt }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1">
                        <a
                            :href="`/rules/${rule.slug}`"
                            target="_blank"
                            class="p-2 rounded-lg text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors"
                            title="Preview"
                        >
                            <ExternalLink :size="14" :stroke-width="1.75" />
                        </a>
                        <Link
                            :href="route('admin.rules.edit', rule.slug)"
                            class="p-2 rounded-lg text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors"
                            title="Edit"
                        >
                            <Pencil :size="14" :stroke-width="1.75" />
                        </Link>
                        <button
                            v-if="!rule.is_system"
                            type="button"
                            class="p-2 rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-400/10 transition-colors"
                            title="Delete"
                            @click="deleteRule(rule.slug)"
                        >
                            <Trash2 :size="14" :stroke-width="1.75" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
