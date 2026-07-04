<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, Paintbrush, CheckCircle2, ChevronRight, Info } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { computed } from 'vue';

interface Theme {
    id: number;
    name: string;
    slug: string;
    version: string;
    author: string;
    description: string;
    type: string;
    active: boolean;
    preview_image_url: string | null;
    installed_at: string | null;
    activated_at: string | null;
}

const props = defineProps<{ themes: Theme[] }>();

const summary = computed(() => ({
    total:  props.themes.length,
    active: props.themes.filter(t => t.active).length,
}));

const typeConfig: Record<string, { label: string; cls: string }> = {
    official:  { label: 'Official',  cls: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
    community: { label: 'Community', cls: 'bg-violet-500/10 text-violet-400 border-violet-500/20' },
    custom:    { label: 'Custom',    cls: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
};
function typeConf(type: string) {
    return typeConfig[type] ?? typeConfig.custom;
}

function syncThemes() {
    router.post(route('admin.themes.sync'));
}

function activateTheme(theme: Theme) {
    router.post(route('admin.themes.activate', theme.id));
}

function deactivateTheme(theme: Theme) {
    router.post(route('admin.themes.deactivate', theme.id));
}
</script>

<template>
    <Head title="Themes" />
    <AdminLayout title="Themes">

        <PageHeader title="Themes" description="Install and activate public-facing themes." :icon="Paintbrush">
            <template #actions>
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors"
                    @click="syncThemes"
                >
                    <RefreshCw :size="12" :stroke-width="2" />
                    Sync from disk
                </button>
            </template>
        </PageHeader>

        <!-- Summary bar -->
        <div v-if="themes.length > 0" class="grid grid-cols-2 gap-3 mb-5 max-w-xs">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3 flex items-center gap-3">
                <Paintbrush :size="16" :stroke-width="1.75" class="text-zinc-500 shrink-0" />
                <div>
                    <p class="text-2xl font-bold text-zinc-100 leading-none tabular-nums">{{ summary.total }}</p>
                    <p class="text-xs text-zinc-600 mt-0.5">Installed</p>
                </div>
            </div>
            <div class="bg-emerald-500/5 border border-emerald-500/15 rounded-xl px-4 py-3 flex items-center gap-3">
                <CheckCircle2 :size="16" :stroke-width="1.75" class="text-emerald-400 shrink-0" />
                <div>
                    <p class="text-2xl font-bold text-emerald-400 leading-none tabular-nums">{{ summary.active }}</p>
                    <p class="text-xs text-zinc-600 mt-0.5">Active</p>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <template v-if="themes.length === 0">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-16 text-center mb-4">
                <div class="w-12 h-12 rounded-2xl mx-auto mb-4 flex items-center justify-center border bg-zinc-800 border-zinc-700">
                    <Paintbrush :size="22" :stroke-width="1.5" class="text-zinc-500" />
                </div>
                <p class="text-zinc-200 text-base font-semibold mb-1">No themes found</p>
                <p class="text-zinc-500 text-sm mb-6">
                    Place theme folders inside
                    <code class="font-mono text-blue-400">themes/</code>
                    then sync from disk.
                </p>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-400 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors"
                    @click="syncThemes"
                >
                    <RefreshCw :size="13" :stroke-width="2" />
                    Sync from disk
                </button>
            </div>

            <div class="flex items-start gap-3 bg-blue-500/5 border border-blue-500/20 rounded-xl px-4 py-4">
                <Info :size="15" :stroke-width="1.75" class="text-blue-400 mt-0.5 shrink-0" />
                <div>
                    <p class="text-zinc-100 text-sm font-semibold mb-1">How themes work</p>
                    <p class="text-zinc-400 text-xs leading-relaxed">
                        Each theme lives in
                        <code class="text-blue-400 font-mono">themes/{Name}/</code>
                        with a <code class="text-blue-400 font-mono">theme.json</code> manifest.
                        Sync to detect themes, then activate one to apply it to the public site.
                    </p>
                </div>
            </div>
        </template>

        <!-- Theme list -->
        <div v-else class="flex flex-col gap-3">
            <div
                v-for="theme in themes"
                :key="theme.id"
                class="bg-[#111113] border rounded-xl overflow-hidden"
                :class="theme.active ? 'border-blue-500/40' : 'border-zinc-800/70'"
            >
                <div class="flex items-center gap-4 p-4">
                    <!-- Preview thumbnail -->
                    <div class="w-20 h-14 rounded-lg bg-zinc-900/60 border border-zinc-800/70 flex items-center justify-center shrink-0 overflow-hidden">
                        <img v-if="theme.preview_image_url" :src="theme.preview_image_url" :alt="theme.name" class="w-full h-full object-cover" />
                        <Paintbrush v-else :size="18" :stroke-width="1.5" class="text-zinc-600" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-zinc-100 font-semibold text-sm">{{ theme.name }}</span>
                            <span
                                v-if="theme.active"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border-emerald-500/20"
                            >
                                <CheckCircle2 :size="9" :stroke-width="2.5" />
                                Active
                            </span>
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-semibold"
                                :class="typeConf(theme.type).cls"
                            >{{ typeConf(theme.type).label }}</span>
                        </div>
                        <p class="text-zinc-600 text-xs font-mono mt-0.5">{{ theme.slug }} · v{{ theme.version }}</p>
                        <p v-if="theme.description" class="text-zinc-500 text-xs mt-1 truncate">{{ theme.description }}</p>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button
                            v-if="!theme.active"
                            type="button"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                            @click="activateTheme(theme)"
                        >
                            <CheckCircle2 :size="12" :stroke-width="2" />
                            Activate
                        </button>
                        <button
                            v-else
                            type="button"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-500 hover:text-zinc-300 hover:border-zinc-600 transition-colors"
                            @click="deactivateTheme(theme)"
                        >
                            Deactivate
                        </button>
                        <Link
                            :href="route('admin.themes.show', theme.id)"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                        >
                            <ChevronRight :size="13" :stroke-width="1.75" />
                        </Link>
                    </div>
                </div>

                <div v-if="theme.active" class="px-4 py-2 border-t border-blue-500/20 bg-blue-500/5 text-[10px] text-blue-400/70 font-medium">
                    Currently active — applied to the public website
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
