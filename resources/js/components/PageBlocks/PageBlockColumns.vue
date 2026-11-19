<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Server, Shield, Trophy, Users, Zap, Star, Heart, Gamepad2, Gift, Crown, Rocket, MessageCircle, Clock, Globe, Lock } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { proseClasses } from './prose';

const props = defineProps<{ data: Record<string, any>; props: { html: string[] } }>();

// Same names as CorePageBlocks::COLUMN_ICONS.
const ICONS: Record<string, Component> = { Server, Shield, Trophy, Users, Zap, Star, Heart, Gamepad2, Gift, Crown, Rocket, MessageCircle, Clock, Globe, Lock };

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const columns = computed<any[]>(() => props.data.columns ?? []);
const grid = computed(() => (columns.value.length >= 3 ? 'md:grid-cols-3' : columns.value.length === 2 ? 'md:grid-cols-2' : ''));
</script>

<template>
    <div v-if="columns.length" class="grid grid-cols-1 gap-4" :class="grid">
        <div v-for="(col, i) in columns" :key="i" class="rounded-xl p-6 ring-1"
            :class="dark ? 'bg-[#111113] ring-white/[0.06]' : 'bg-white ring-zinc-200'">
            <span v-if="ICONS[col.icon]" class="mb-4 inline-flex w-10 h-10 items-center justify-center rounded-lg bg-hc-accent/10 text-hc-accent">
                <component :is="ICONS[col.icon]" :size="19" :stroke-width="1.9" aria-hidden="true" />
            </span>
            <h3 v-if="col.title" class="text-[16px] font-bold mb-2" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ col.title }}</h3>
            <!-- Server-rendered from Markdown with raw HTML stripped (SafeMarkdown). -->
            <div v-if="props.props.html[i]" class="prose prose-sm text-[14px]" :class="proseClasses(dark)" v-html="props.props.html[i]" />
        </div>
    </div>
</template>
