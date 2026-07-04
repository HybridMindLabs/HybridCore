<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    List, Plus, ChevronLeft, ChevronUp, ChevronDown,
    Trash2, Indent, Outdent, ExternalLink, Check, X, Pencil,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { ref, computed } from 'vue';

interface MenuItemRow {
    id: number; label: string; url: string; target: string;
    sort: number; parent_id: number | null; children: MenuItemRow[];
}
interface MenuData { id: number; name: string; slug: string; location: string | null }

const props = defineProps<{ menu: MenuData; items: MenuItemRow[] }>();

function flatten(items: MenuItemRow[], depth = 0): Array<MenuItemRow & { depth: number }> {
    const result: Array<MenuItemRow & { depth: number }> = [];
    for (const item of items) {
        result.push({ ...item, depth });
        if (item.children?.length) result.push(...flatten(item.children, depth + 1));
    }
    return result;
}

const flatItems = ref(flatten(props.items));

const settingsForm = useForm({
    name: props.menu.name,
    location: props.menu.location ?? '',
});

function saveSettings() {
    settingsForm.put(route('admin.menus.update', props.menu.id));
}

const addForm = useForm({ label: '', url: '', target: '_self', parent_id: null as number | null });

function addItem() {
    addForm.post(route('admin.menus.items.store', props.menu.id), {
        onSuccess: () => addForm.reset(),
    });
}

const editing = ref<number | null>(null);
const editData = ref({ label: '', url: '', target: '_self' });

function startEdit(item: MenuItemRow & { depth: number }) {
    editing.value = item.id;
    editData.value = { label: item.label, url: item.url, target: item.target };
}

function saveEdit(item: MenuItemRow & { depth: number }) {
    router.put(route('admin.menus.items.update', [props.menu.id, item.id]), editData.value, {
        onSuccess: () => { editing.value = null; },
        preserveScroll: true,
    });
}

function deleteItem(item: MenuItemRow & { depth: number }) {
    if (confirm(`Remove "${item.label}"?`)) {
        router.delete(route('admin.menus.items.destroy', [props.menu.id, item.id]), { preserveScroll: true });
    }
}

function moveUp(idx: number) {
    const cur = flatItems.value[idx];
    for (let i = idx - 1; i >= 0; i--) {
        if (flatItems.value[i].depth === cur.depth && flatItems.value[i].parent_id === cur.parent_id) {
            const tmp = flatItems.value[i].sort; flatItems.value[i].sort = cur.sort; cur.sort = tmp;
            const arr = [...flatItems.value]; [arr[i], arr[idx]] = [arr[idx], arr[i]];
            flatItems.value = arr; saveOrder(); return;
        }
    }
}

function moveDown(idx: number) {
    const cur = flatItems.value[idx];
    for (let i = idx + 1; i < flatItems.value.length; i++) {
        if (flatItems.value[i].depth === cur.depth && flatItems.value[i].parent_id === cur.parent_id) {
            const tmp = flatItems.value[i].sort; flatItems.value[i].sort = cur.sort; cur.sort = tmp;
            const arr = [...flatItems.value]; [arr[i], arr[idx]] = [arr[idx], arr[i]];
            flatItems.value = arr; saveOrder(); return;
        }
    }
}

function indent(idx: number) {
    const cur = flatItems.value[idx];
    if (cur.depth >= 1) return;
    for (let i = idx - 1; i >= 0; i--) {
        if (flatItems.value[i].depth === cur.depth) {
            cur.parent_id = flatItems.value[i].id; cur.depth++; saveOrder(); return;
        }
    }
}

function outdent(idx: number) {
    const cur = flatItems.value[idx];
    if (cur.depth === 0) return;
    cur.parent_id = null; cur.depth = 0; saveOrder();
}

function saveOrder() {
    router.post(route('admin.menus.reorder', props.menu.id), {
        items: flatItems.value.map((item, i) => ({ id: item.id, sort: (i + 1) * 10, parent_id: item.parent_id })),
    }, { preserveScroll: true });
}

const topLevelItems = computed(() => flatItems.value.filter((i) => i.depth === 0));

const LOCATIONS: Record<string, string> = {
    header:       'Header',
    footer_legal: 'Footer — Legal',
    footer_links: 'Footer — Links',
    sidebar:      'Sidebar',
};

const inputClass = 'bg-zinc-900/60 border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 w-full';
const smallInputClass = 'bg-zinc-900/60 border border-zinc-800/70 rounded-lg px-2 py-1.5 text-xs text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500';
</script>

<template>
    <Head :title="`Edit Menu — ${menu.name}`" />
    <AdminLayout title="Edit Menu">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm">
            <Link :href="route('admin.menus.index')" class="flex items-center gap-1.5 text-zinc-500 hover:text-zinc-100 transition-colors">
                <ChevronLeft :size="13" :stroke-width="1.75" /> Menus
            </Link>
            <span class="text-zinc-700">/</span>
            <span class="text-zinc-300">{{ menu.name }}</span>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 max-w-5xl">

            <!-- Left: tree builder (2/3) -->
            <div class="xl:col-span-2 flex flex-col gap-4">

                <!-- Items tree -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-3 border-b border-zinc-800/70">
                        <List :size="14" :stroke-width="1.75" class="text-blue-400" />
                        <span class="text-sm font-semibold text-zinc-100">Menu Items</span>
                        <span class="ml-auto text-xs text-zinc-600">{{ flatItems.length }} item{{ flatItems.length === 1 ? '' : 's' }}</span>
                    </div>

                    <div v-if="flatItems.length === 0" class="px-5 py-12 text-center">
                        <List :size="24" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                        <p class="text-zinc-600 text-sm">No items yet.</p>
                        <p class="text-zinc-700 text-xs mt-1">Add your first link below.</p>
                    </div>

                    <div v-else class="divide-y divide-zinc-800/50">
                        <div
                            v-for="(item, idx) in flatItems"
                            :key="item.id"
                            class="pr-3 py-2.5 group"
                            :style="{ paddingLeft: `${1.25 + item.depth * 1.5}rem` }"
                        >
                            <!-- Edit mode -->
                            <div v-if="editing === item.id" class="flex items-center gap-2 flex-wrap">
                                <input v-model="editData.label" type="text" :class="[smallInputClass, 'flex-1 min-w-24']" placeholder="Label" />
                                <input v-model="editData.url" type="text" :class="[smallInputClass, 'flex-1 min-w-32 font-mono']" placeholder="/url" />
                                <select v-model="editData.target" :class="smallInputClass">
                                    <option value="_self">Same tab</option>
                                    <option value="_blank">New tab</option>
                                </select>
                                <button type="button" class="w-7 h-7 flex items-center justify-center rounded-lg text-emerald-400 hover:bg-emerald-500/10 transition-colors" @click="saveEdit(item)">
                                    <Check :size="14" :stroke-width="2" />
                                </button>
                                <button type="button" class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="editing = null">
                                    <X :size="14" :stroke-width="2" />
                                </button>
                            </div>

                            <!-- View mode -->
                            <div v-else class="flex items-center gap-2">
                                <span v-if="item.depth > 0" class="text-zinc-700 mr-1 text-xs">└</span>
                                <div class="flex-1 min-w-0">
                                    <span class="text-zinc-200 text-sm">{{ item.label }}</span>
                                    <span class="text-zinc-600 text-xs font-mono ml-2 truncate">{{ item.url }}</span>
                                    <ExternalLink v-if="item.target === '_blank'" :size="10" :stroke-width="1.75" class="inline ml-1 text-zinc-700" />
                                </div>

                                <!-- Actions — always visible -->
                                <div class="flex items-center gap-0.5 shrink-0">
                                    <button v-if="item.depth === 0" type="button" title="Make child" class="w-6 h-6 flex items-center justify-center rounded text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors" @click="indent(idx)">
                                        <Indent :size="11" :stroke-width="1.75" />
                                    </button>
                                    <button v-if="item.depth > 0" type="button" title="Make top-level" class="w-6 h-6 flex items-center justify-center rounded text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors" @click="outdent(idx)">
                                        <Outdent :size="11" :stroke-width="1.75" />
                                    </button>
                                    <button type="button" title="Move up" class="w-6 h-6 flex items-center justify-center rounded text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors" @click="moveUp(idx)">
                                        <ChevronUp :size="12" :stroke-width="1.75" />
                                    </button>
                                    <button type="button" title="Move down" class="w-6 h-6 flex items-center justify-center rounded text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors" @click="moveDown(idx)">
                                        <ChevronDown :size="12" :stroke-width="1.75" />
                                    </button>
                                    <button type="button" title="Edit" class="w-6 h-6 flex items-center justify-center rounded text-zinc-600 hover:text-blue-400 hover:bg-blue-500/10 transition-colors" @click="startEdit(item)">
                                        <Pencil :size="11" :stroke-width="1.75" />
                                    </button>
                                    <button type="button" title="Delete" class="w-6 h-6 flex items-center justify-center rounded text-zinc-600 hover:text-red-400 hover:bg-red-500/10 transition-colors" @click="deleteItem(item)">
                                        <Trash2 :size="11" :stroke-width="1.75" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add item -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-zinc-100 mb-4 flex items-center gap-2">
                        <Plus :size="14" :stroke-width="2" class="text-blue-400" /> Add Item
                    </h3>
                    <form class="flex flex-col gap-3" @submit.prevent="addItem">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-zinc-500 text-xs font-medium">Label <span class="text-red-400">*</span></label>
                                <input v-model="addForm.label" type="text" :class="inputClass" placeholder="Privacy Policy" />
                                <p v-if="addForm.errors.label" class="text-xs text-red-400">{{ addForm.errors.label }}</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-zinc-500 text-xs font-medium">URL <span class="text-red-400">*</span></label>
                                <input v-model="addForm.url" type="text" :class="inputClass + ' font-mono'" placeholder="/privacy-policy" />
                                <p v-if="addForm.errors.url" class="text-xs text-red-400">{{ addForm.errors.url }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-zinc-500 text-xs font-medium">Open in</label>
                                <select v-model="addForm.target" :class="inputClass">
                                    <option value="_self">Same tab</option>
                                    <option value="_blank">New tab</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-zinc-500 text-xs font-medium">Parent item <span class="text-zinc-700 font-normal">optional</span></label>
                                <select v-model="addForm.parent_id" :class="inputClass">
                                    <option :value="null">— Top level —</option>
                                    <option v-for="top in topLevelItems" :key="top.id" :value="top.id">{{ top.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="addForm.processing"
                                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                            >
                                <Plus :size="14" :stroke-width="2" /> Add Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: menu settings -->
            <div>
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-zinc-100 mb-4">Menu Settings</h3>
                    <form class="flex flex-col gap-4" @submit.prevent="saveSettings">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-500 text-xs font-medium">Name</label>
                            <input v-model="settingsForm.name" type="text" :class="inputClass" />
                            <p v-if="settingsForm.errors.name" class="text-xs text-red-400">{{ settingsForm.errors.name }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-500 text-xs font-medium">Location</label>
                            <select v-model="settingsForm.location" :class="inputClass">
                                <option value="">— None —</option>
                                <option v-for="(label, key) in LOCATIONS" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div class="text-xs text-zinc-700 pt-2 border-t border-zinc-800/50">
                            Slug: <span class="font-mono text-zinc-600">{{ menu.slug }}</span>
                        </div>
                        <button
                            type="submit"
                            :disabled="settingsForm.processing"
                            class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                        >
                            {{ settingsForm.processing ? 'Saving…' : 'Save Settings' }}
                        </button>
                    </form>
                </div>
            </div>

        </div>

    </AdminLayout>
</template>
