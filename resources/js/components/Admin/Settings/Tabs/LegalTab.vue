<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Scale } from '@lucide/vue';
</script>

<template>
    <div class="max-w-lg">
        <Link
            :href="route('admin.legal.index')"
            class="flex items-center justify-between px-5 py-4 bg-admin-surface border border-zinc-800/70 rounded-xl hover:border-blue-500/40 hover:bg-blue-500/5 transition-colors group"
        >
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-zinc-800/60 border border-zinc-700/50 flex items-center justify-center shrink-0">
                    <Scale :size="15" :stroke-width="1.75" class="text-zinc-400" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-200 group-hover:text-white transition-colors">Manage Legal Pages</p>
                    <p class="text-xs text-zinc-600 mt-0.5">Create and edit Terms, Privacy, Cookies and custom legal pages</p>
                </div>
            </div>
            <ArrowRight :size="15" :stroke-width="1.75" class="text-zinc-600 group-hover:text-blue-400 transition-colors shrink-0" />
        </Link>
    </div>
</template>
