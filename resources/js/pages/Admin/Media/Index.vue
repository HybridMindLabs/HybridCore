<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import MediaBrowser from '@/components/Admin/Media/MediaBrowser.vue';
</script>

<template>
    <Head title="Media library" />
    <AdminLayout title="Media library">
        <PageHeader title="Media library" description="Every image on the site in one place. Uploads are resized and saved as WebP, animations included." />
        <MediaBrowser mode="manage" />
    </AdminLayout>
</template>
