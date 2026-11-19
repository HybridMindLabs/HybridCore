<script setup lang="ts">
import { ExternalLink, WrenchIcon } from '@lucide/vue';

defineProps<{ extensions: { slug: string; label: string; url: string; permission: string | null }[] }>();
</script>

<template>
    <div>
        <div v-if="extensions.length" class="bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-800/60 bg-admin-elevated">
                <h3 class="text-zinc-100 text-sm font-semibold">Installed Extension Settings</h3>
                <p class="text-zinc-500 text-xs mt-0.5">Each enabled extension that provides settings has its own dedicated page.</p>
            </div>
            <div class="divide-y divide-zinc-800/50">
                <a
                    v-for="ext in extensions"
                    :key="ext.slug"
                    :href="ext.url"
                    class="flex items-center justify-between px-5 py-4 hover:bg-white/[0.02] transition-colors group"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-zinc-800 border border-zinc-700/60 flex items-center justify-center">
                            <WrenchIcon :size="14" :stroke-width="1.75" class="text-zinc-400" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-200 group-hover:text-white transition-colors">{{ ext.label }}</p>
                            <p class="text-xs text-zinc-600 font-mono">/admin/settings/extensions/{{ ext.slug }}</p>
                        </div>
                    </div>
                    <ExternalLink :size="13" :stroke-width="1.75" class="text-zinc-700 group-hover:text-zinc-400 transition-colors shrink-0" />
                </a>
            </div>
        </div>

        <div v-else class="bg-admin-surface border border-zinc-800/70 rounded-xl p-10 text-center">
            <WrenchIcon :size="22" :stroke-width="1.5" class="mx-auto mb-2 text-zinc-700" />
            <p class="text-zinc-500 text-sm">No extensions with settings are currently enabled.</p>
        </div>
    </div>
</template>
