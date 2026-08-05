<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Puzzle } from '@lucide/vue';

defineProps<{ message: string }>();
</script>

<template>
    <Head title="Demo" />
    <PublicLayout>
        <div class="max-w-3xl mx-auto px-4 py-16 text-center">
            <Puzzle :size="32" :stroke-width="1.5" class="mx-auto mb-4 text-zinc-400" />
            <p class="text-zinc-500 text-sm">{{ message }}</p>
        </div>
    </PublicLayout>
</template>
