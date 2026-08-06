<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Star, Trash2, MessageSquare, Search, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import EmptyState from '@/components/UI/EmptyState.vue';

interface Review {
    id: number;
    rating: number;
    body: string | null;
    created_at: string;
    user: { id: number; name: string; username: string } | null;
    server: { id: number; label: string; game: { name: string; color: string } | null } | null;
}

interface PaginatedReviews {
    data: Review[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{ reviews: PaginatedReviews; total: number; filters: { search: string } }>();

const search = ref(props.filters.search);
const searching = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.servers.reviews'), value ? { search: value } : {}, {
            preserveState: true, preserveScroll: true, replace: true,
            onStart: () => { searching.value = true; },
            onFinish: () => { searching.value = false; },
        });
    }, 350);
});

function del(id: number) {
    if (!confirm('Delete this review?')) return;
    router.delete(route('admin.servers.reviews.destroy', id));
}

function stars(n: number) {
    return Array.from({ length: 5 }, (_, i) => i < n);
}
</script>

<template>
    <Head title="Server Reviews" />
    <AdminLayout title="Server Reviews">

        <PageHeader title="Server Reviews" description="All user reviews across every server." :icon="MessageSquare">
            <template #actions>
                <span class="text-xs text-zinc-600">{{ total }} total</span>
            </template>
        </PageHeader>

        <!-- Search — matters once reviews pile up across many servers -->
        <div v-if="total > 0" class="relative max-w-sm mb-4">
            <Search :size="14" :stroke-width="1.75" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600 pointer-events-none" />
            <input
                v-model="search"
                type="text"
                placeholder="Search by user, server, or review text…"
                class="w-full bg-[#111113] border border-zinc-800/70 text-zinc-100 rounded-lg pl-9 pr-9 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
            />
            <button v-if="search" type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-600 hover:text-zinc-400" @click="search = ''">
                <X :size="14" :stroke-width="1.75" />
            </button>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="reviews.data.length === 0"
            :icon="search ? Search : MessageSquare"
            :title="search ? 'No matches' : 'No reviews yet'"
            :description="search ? `No reviews match &quot;${search}&quot;.` : undefined"
        />

        <!-- Table -->
        <div v-else class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden transition-opacity" :class="searching ? 'opacity-50 pointer-events-none' : ''">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">Server</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">Rating</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">Review</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/40">
                    <tr
                        v-for="review in reviews.data"
                        :key="review.id"
                        class="hover:bg-zinc-900/30 transition-colors"
                    >
                        <!-- User -->
                        <td class="px-4 py-3">
                            <span v-if="review.user" class="text-zinc-200 font-medium">{{ review.user.name }}</span>
                            <span v-else class="text-zinc-600 italic">deleted</span>
                        </td>

                        <!-- Server -->
                        <td class="px-4 py-3">
                            <div v-if="review.server" class="flex items-center gap-2">
                                <span
                                    v-if="review.server.game"
                                    class="w-2 h-2 rounded-full shrink-0"
                                    :style="{ background: review.server.game.color }"
                                />
                                <span class="text-zinc-300 font-mono text-xs truncate max-w-[160px]">{{ review.server.label }}</span>
                            </div>
                            <span v-else class="text-zinc-600 italic text-xs">deleted</span>
                        </td>

                        <!-- Rating -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-0.5">
                                <Star
                                    v-for="(filled, i) in stars(review.rating)"
                                    :key="i"
                                    :size="12"
                                    :stroke-width="1.5"
                                    :class="filled ? 'text-amber-400 fill-amber-400' : 'text-zinc-700'"
                                />
                            </div>
                        </td>

                        <!-- Body -->
                        <td class="px-4 py-3 max-w-[260px]">
                            <p v-if="review.body" class="text-zinc-400 text-xs truncate">{{ review.body }}</p>
                            <span v-else class="text-zinc-700 text-xs italic">no text</span>
                        </td>

                        <!-- Date -->
                        <td class="px-4 py-3 text-xs text-zinc-600 whitespace-nowrap">{{ review.created_at }}</td>

                        <!-- Actions -->
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="p-1.5 rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-400/10 transition-colors"
                                title="Delete review"
                                @click="del(review.id)"
                            >
                                <Trash2 :size="13" :stroke-width="1.75" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="reviews.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-zinc-800/70">
                <span class="text-xs text-zinc-600">Page {{ reviews.current_page }} of {{ reviews.last_page }}</span>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="reviews.current_page > 1"
                        :href="route('admin.servers.reviews', { page: reviews.current_page - 1, search: search || undefined })"
                        class="px-3 py-1.5 rounded-lg text-xs border border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                    >Previous</Link>
                    <Link
                        v-if="reviews.current_page < reviews.last_page"
                        :href="route('admin.servers.reviews', { page: reviews.current_page + 1, search: search || undefined })"
                        class="px-3 py-1.5 rounded-lg text-xs border border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                    >Next</Link>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
