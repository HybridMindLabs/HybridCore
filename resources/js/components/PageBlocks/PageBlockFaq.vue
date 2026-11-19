<script setup lang="ts">
import { computed } from 'vue';
import { ChevronDown } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { proseClasses } from './prose';

const props = defineProps<{ data: Record<string, any>; props: { answers: string[] } }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const items = computed(() => (props.data.items ?? []).map((item: any, i: number) => ({ ...item, html: props.props.answers[i] ?? '' }))
    .filter((item: any) => item.question));
</script>

<template>
    <div v-if="items.length" class="max-w-3xl mx-auto">
        <h2 v-if="data.title" class="mb-4 text-[22px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ data.title }}</h2>
        <div class="rounded-xl ring-1 divide-y" :class="dark ? 'bg-[#111113] ring-white/[0.06] divide-white/[0.06]' : 'bg-white ring-zinc-200 divide-zinc-200'">
            <details v-for="(item, i) in items" :key="i" class="group">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-[15px] font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ item.question }}
                    <ChevronDown :size="16" class="shrink-0 transition-transform group-open:rotate-180" :class="dark ? 'text-zinc-500' : 'text-zinc-400'" aria-hidden="true" />
                </summary>
                <!-- Server-rendered from Markdown with raw HTML stripped (SafeMarkdown). -->
                <div v-if="item.html" class="px-5 pb-5 prose prose-sm" :class="proseClasses(dark)" v-html="item.html" />
            </details>
        </div>
    </div>
</template>
