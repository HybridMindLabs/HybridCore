<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { List, Plus, Pencil, Trash2, MapPin, Hash } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface MenuRow { id: number; name: string; slug: string; location: string | null; items_count: number }

defineProps<{ menus: MenuRow[] }>();

const menuForm = useForm({ name: '', location: '' });

function createMenu() {
    menuForm.post(route('admin.menus.store'), { onSuccess: () => menuForm.reset() });
}

function deleteMenu(menu: MenuRow) {
    if (confirm(`Delete menu "${menu.name}" and all its items?`)) {
        router.delete(route('admin.menus.destroy', menu.id));
    }
}

const LOCATIONS: Record<string, string> = {
    header:       'Header',
    footer_legal: 'Footer — Legal',
    footer_links: 'Footer — Links',
    sidebar:      'Sidebar',
};

const inputClass = 'bg-zinc-900/60 border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 w-full';
</script>

<template>
    <Head title="Menus" />
    <AdminLayout title="Menus">

        <PageHeader title="Menus" description="Navigation menus displayed on the public website." :icon="List" />

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            <!-- Left: menus list -->
            <div class="flex flex-col gap-3">

                <!-- Empty state -->
                <div v-if="menus.length === 0" class="bg-[#111113] border border-zinc-800/70 rounded-xl px-6 py-16 text-center">
                    <List :size="32" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                    <p class="text-zinc-500 text-sm font-medium">No menus yet</p>
                    <p class="text-zinc-700 text-xs mt-1">Create a menu using the form →</p>
                </div>

                <!-- Menu cards -->
                <div
                    v-for="menu in menus"
                    :key="menu.id"
                    class="bg-[#111113] border border-zinc-800/70 rounded-xl px-5 py-4 flex items-center justify-between hover:border-zinc-700/60 transition-colors group"
                >
                    <div class="min-w-0 flex-1">
                        <!-- Name + location badge -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-zinc-100 text-sm font-semibold">{{ menu.name }}</span>
                            <span
                                v-if="menu.location"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20"
                            >
                                <MapPin :size="9" :stroke-width="2" />
                                {{ LOCATIONS[menu.location] ?? menu.location }}
                            </span>
                        </div>

                        <!-- Meta row -->
                        <div class="flex items-center gap-3 mt-1.5 text-xs text-zinc-600">
                            <span>{{ menu.items_count }} item{{ menu.items_count === 1 ? '' : 's' }}</span>
                            <span class="text-zinc-800">·</span>
                            <span class="font-mono flex items-center gap-1">
                                <Hash :size="10" :stroke-width="1.75" />{{ menu.slug }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1 shrink-0 ml-4">
                        <Link
                            :href="route('admin.menus.edit', menu.id)"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 hover:bg-zinc-800/50 transition-colors"
                        >
                            <Pencil :size="11" :stroke-width="1.75" /> Edit items
                        </Link>
                        <button
                            type="button"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                            title="Delete menu"
                            @click="deleteMenu(menu)"
                        >
                            <Trash2 :size="13" :stroke-width="1.75" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: create form (sticky) -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-zinc-100 mb-1 flex items-center gap-2">
                    <Plus :size="14" :stroke-width="2" class="text-blue-400" />
                    Create New Menu
                </h3>
                <p class="text-zinc-600 text-xs mb-4">Menus can be placed in specific locations on the site.</p>

                <form class="flex flex-col gap-4" @submit.prevent="createMenu">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-500 text-xs font-medium">Name <span class="text-red-400">*</span></label>
                        <input
                            v-model="menuForm.name"
                            type="text"
                            :class="[inputClass, menuForm.errors.name ? 'border-red-500' : '']"
                            placeholder="e.g. Footer Legal"
                        />
                        <p v-if="menuForm.errors.name" class="text-xs text-red-400">{{ menuForm.errors.name }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-500 text-xs font-medium">
                            Location <span class="text-zinc-700 font-normal">optional</span>
                        </label>
                        <select v-model="menuForm.location" :class="inputClass">
                            <option value="">— None —</option>
                            <option v-for="(label, key) in LOCATIONS" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <p class="text-zinc-700 text-[11px] leading-relaxed">
                            Assigns this menu to a slot in the theme layout so it renders automatically.
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="menuForm.processing"
                        class="w-full flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                    >
                        <Plus :size="14" :stroke-width="2" />
                        {{ menuForm.processing ? 'Creating…' : 'Create Menu' }}
                    </button>
                </form>

                <!-- Location reference -->
                <div v-if="Object.keys(LOCATIONS).length" class="mt-4 pt-4 border-t border-zinc-800/50">
                    <p class="text-zinc-600 text-[11px] font-medium uppercase tracking-wider mb-2">Available locations</p>
                    <div class="flex flex-col gap-1">
                        <div v-for="(label, key) in LOCATIONS" :key="key" class="flex items-center justify-between text-xs">
                            <span class="text-zinc-500">{{ label }}</span>
                            <span class="font-mono text-zinc-700 text-[10px]">{{ key }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </AdminLayout>
</template>
