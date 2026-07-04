<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { Puzzle } from '@lucide/vue';

defineProps<{ message: string }>();
</script>

<template>
    <Head title="Demo" />
    <AdminLayout title="Demo">
        <PageHeader title="Demo" :icon="Puzzle" />
        <div class="bg-[#111827] border border-[#1e2d47] rounded-xl p-5 text-[#94a3b8] text-sm">
            {{ message }}
        </div>
    </AdminLayout>
</template>