<script setup lang="ts">
import { router } from '@inertiajs/vue3';

interface PageLink { url: string | null; label: string; active: boolean }

interface Paginator {
    links: PageLink[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{ paginator: Paginator }>();

function go(url: string | null) {
    if (! url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <div v-if="paginator.last_page > 1" class="flex items-center justify-between gap-4 px-4 py-3 border-t border-zinc-800/70">
        <p class="text-zinc-500 text-xs">
            Showing <span class="text-zinc-400 font-medium">{{ paginator.from }}–{{ paginator.to }}</span>
            of <span class="text-zinc-400 font-medium">{{ paginator.total }}</span>
        </p>
        <div class="flex items-center gap-1">
            <button
                v-for="(link, i) in paginator.links"
                :key="i"
                type="button"
                :disabled="! link.url"
                class="min-w-[28px] h-7 px-2 rounded-md text-xs transition-colors"
                :class="link.active
                    ? 'bg-blue-500 text-[#0a0f1a] font-semibold'
                    : link.url
                        ? 'text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-100'
                        : 'text-zinc-700 cursor-not-allowed'"
                @click="go(link.url)"
                v-html="link.label"
            />
        </div>
    </div>
</template>
