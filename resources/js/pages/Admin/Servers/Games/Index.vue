<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Gamepad2, Plus, Pencil, Trash2, Check, X } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { ref } from 'vue';

interface GameRow {
    id: number; name: string; slug: string; icon: string; color: string;
    query_driver: string; default_port: number; is_active: boolean;
    sort_order: number; servers_count: number;
}

const props = defineProps<{ games: GameRow[] }>();

// --- Add form ---
const showAdd = ref(false);
const addForm = useForm({
    name: '', slug: '', icon: 'gamepad-2', color: '#3b82f6',
    query_driver: 'source', default_port: 27015, is_active: true, sort_order: 0,
});

function submitAdd() {
    addForm.post(route('admin.servers.games.store'), {
        onSuccess: () => { addForm.reset(); showAdd.value = false; },
    });
}

// --- Edit ---
const editing = ref<number | null>(null);
const editForm = useForm({
    name: '', slug: '', icon: '', color: '',
    query_driver: '', default_port: 0, is_active: true, sort_order: 0,
});

function startEdit(g: GameRow) {
    editing.value = g.id;
    editForm.name = g.name;
    editForm.slug = g.slug;
    editForm.icon = g.icon;
    editForm.color = g.color;
    editForm.query_driver = g.query_driver;
    editForm.default_port = g.default_port;
    editForm.is_active = g.is_active;
    editForm.sort_order = g.sort_order;
}

function submitEdit(id: number) {
    editForm.put(route('admin.servers.games.update', id), {
        onSuccess: () => { editing.value = null; },
    });
}

function destroy(g: GameRow) {
    if (confirm(`Delete game "${g.name}"? This will also delete all its servers.`)) {
        router.delete(route('admin.servers.games.destroy', g.id));
    }
}

const drivers = [
    'source', 'goldensrc', 'minecraft_java', 'minecraft_bedrock',
    'fivem', 'arkse', 'sevendaystodie', 'gmod', 'tf2', 'unturned',
];
</script>

<template>
    <Head title="Games" />
    <AdminLayout title="Games">

        <PageHeader title="Games" description="Manage supported game types for the server browser." :icon="Gamepad2">
            <template #actions>
                <Link :href="route('admin.servers.index')" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900/60 border border-zinc-800/70 transition-colors">
                    Servers
                </Link>
                <button type="button" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-[#0a0f1a] hover:bg-blue-600 transition-colors" @click="showAdd = !showAdd">
                    <Plus :size="14" :stroke-width="2" />
                    Add Game
                </button>
            </template>
        </PageHeader>

        <!-- Add Game Panel -->
        <div v-if="showAdd" class="mb-6 bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-zinc-100 mb-4">Add New Game</h3>
            <form @submit.prevent="submitAdd" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Name <span class="text-red-400">*</span></label>
                    <input v-model="addForm.name" type="text" placeholder="Counter-Strike 2" class="w-full bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder-[#475569] focus:outline-none focus:border-blue-500/50" />
                    <p v-if="addForm.errors.name" class="mt-1 text-xs text-red-400">{{ addForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Slug <span class="text-red-400">*</span></label>
                    <input v-model="addForm.slug" type="text" placeholder="cs2" class="w-full bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 font-mono placeholder-[#475569] focus:outline-none focus:border-blue-500/50" />
                    <p v-if="addForm.errors.slug" class="mt-1 text-xs text-red-400">{{ addForm.errors.slug }}</p>
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Query Driver <span class="text-red-400">*</span></label>
                    <select v-model="addForm.query_driver" class="w-full bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:border-blue-500/50">
                        <option v-for="d in drivers" :key="d" :value="d">{{ d }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Default Port</label>
                    <input v-model.number="addForm.default_port" type="number" min="1" max="65535" class="w-full bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:border-blue-500/50" />
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Color</label>
                    <div class="flex items-center gap-2">
                        <input v-model="addForm.color" type="color" class="w-10 h-9 rounded border border-zinc-800/70 bg-[#09090b] cursor-pointer" />
                        <input v-model="addForm.color" type="text" class="flex-1 bg-[#09090b] border border-zinc-800/70 rounded-lg px-2 py-2 text-sm text-zinc-100 font-mono focus:outline-none focus:border-blue-500/50" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Icon</label>
                    <div class="flex items-center gap-3 bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 border" :style="{ background: (addForm.color || '#3b82f6') + '14', borderColor: (addForm.color || '#3b82f6') + '33' }">
                            <GameIcon v-if="addForm.slug" :slug="addForm.slug" :alt="addForm.name" :size="64" img-class="w-7 h-7 object-contain" />
                            <Gamepad2 v-else :size="16" class="text-zinc-600" />
                        </div>
                        <p class="text-zinc-600 text-[11px] leading-snug">
                            Upload <span class="font-mono text-zinc-400">{{ addForm.slug || 'slug' }}.png</span> (or .webp) to
                            <span class="font-mono text-zinc-500">public/images/games/icons/16x16, 32x32, 64x64</span>. Preview updates from the slug.
                        </p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 mb-1">Sort Order</label>
                    <input v-model.number="addForm.sort_order" type="number" min="0" class="w-full bg-[#09090b] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:border-blue-500/50" />
                </div>
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 text-sm text-zinc-400 pb-2">
                        <input v-model="addForm.is_active" type="checkbox" class="w-4 h-4 accent-blue-500" />
                        Active
                    </label>
                </div>
                <div class="col-span-2 sm:col-span-3 lg:col-span-4 flex justify-end gap-3 pt-1">
                    <button type="button" class="px-4 py-2 text-sm text-zinc-500 hover:text-zinc-100 transition-colors" @click="showAdd = false">Cancel</button>
                    <button type="submit" :disabled="addForm.processing" class="px-4 py-2 rounded-lg text-sm font-medium bg-blue-500 text-[#0a0f1a] hover:bg-blue-600 transition-colors disabled:opacity-50">Add Game</button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70 text-zinc-600 text-xs font-medium uppercase tracking-wide">
                        <th class="text-left px-4 py-3">Game</th>
                        <th class="text-left px-4 py-3 hidden sm:table-cell">Driver</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Port</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Servers</th>
                        <th class="text-left px-4 py-3">Active</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="game in games" :key="game.id">
                        <!-- Edit row -->
                        <tr v-if="editing === game.id" class="border-b border-zinc-800/70 bg-[#0f1929]">
                            <td colspan="6" class="px-4 py-4">
                                <form @submit.prevent="submitEdit(game.id)" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <input v-model="editForm.name" type="text" placeholder="Name" class="bg-[#09090b] border border-zinc-800/70 rounded-lg px-2 py-1.5 text-sm text-zinc-100 focus:outline-none focus:border-blue-500/50" />
                                    <input v-model="editForm.slug" type="text" placeholder="slug" class="bg-[#09090b] border border-zinc-800/70 rounded-lg px-2 py-1.5 text-sm text-zinc-100 font-mono focus:outline-none focus:border-blue-500/50" />
                                    <select v-model="editForm.query_driver" class="bg-[#09090b] border border-zinc-800/70 rounded-lg px-2 py-1.5 text-sm text-zinc-100 focus:outline-none focus:border-blue-500/50">
                                        <option v-for="d in drivers" :key="d" :value="d">{{ d }}</option>
                                    </select>
                                    <input v-model.number="editForm.default_port" type="number" min="1" max="65535" class="bg-[#09090b] border border-zinc-800/70 rounded-lg px-2 py-1.5 text-sm text-zinc-100 focus:outline-none focus:border-blue-500/50" />
                                    <div class="flex items-center gap-2">
                                        <input v-model="editForm.color" type="color" class="w-9 h-9 rounded border border-zinc-800/70 bg-[#09090b] cursor-pointer" />
                                        <input v-model="editForm.color" type="text" class="flex-1 bg-[#09090b] border border-zinc-800/70 rounded-lg px-2 py-1.5 text-sm text-zinc-100 font-mono focus:outline-none focus:border-blue-500/50" />
                                    </div>
                                    <input v-model="editForm.icon" type="text" placeholder="icon" class="bg-[#09090b] border border-zinc-800/70 rounded-lg px-2 py-1.5 text-sm text-zinc-100 font-mono focus:outline-none focus:border-blue-500/50" />
                                    <input v-model.number="editForm.sort_order" type="number" min="0" placeholder="Order" class="bg-[#09090b] border border-zinc-800/70 rounded-lg px-2 py-1.5 text-sm text-zinc-100 focus:outline-none focus:border-blue-500/50" />
                                    <label class="flex items-center gap-2 text-sm text-zinc-400">
                                        <input v-model="editForm.is_active" type="checkbox" class="w-4 h-4 accent-blue-500" />
                                        Active
                                    </label>
                                    <div class="col-span-2 sm:col-span-4 flex justify-end gap-2">
                                        <button type="button" class="px-3 py-1.5 text-xs text-zinc-500 hover:text-zinc-100 transition-colors" @click="editing = null">Cancel</button>
                                        <button type="submit" :disabled="editForm.processing" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-500 text-[#0a0f1a] hover:bg-blue-600 transition-colors disabled:opacity-50">Save</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <!-- Normal row -->
                        <tr v-else class="border-b border-zinc-800/70 last:border-0 hover:bg-[#0f1929] transition-colors" :class="!game.is_active ? 'opacity-50' : ''">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 border"
                                        :style="{ background: game.color + '14', borderColor: game.color + '33' }"
                                    >
                                        <GameIcon :slug="game.slug" :alt="game.name" :size="32" img-class="w-6 h-6 object-contain" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-zinc-100 font-medium leading-tight">{{ game.name }}</p>
                                        <p class="text-zinc-600 text-xs font-mono leading-tight">{{ game.slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-400 font-mono text-xs hidden sm:table-cell">{{ game.query_driver }}</td>
                            <td class="px-4 py-3 text-zinc-400 text-xs hidden md:table-cell">{{ game.default_port }}</td>
                            <td class="px-4 py-3 text-zinc-400 text-xs hidden md:table-cell">{{ game.servers_count }}</td>
                            <td class="px-4 py-3">
                                <Check v-if="game.is_active" :size="14" :stroke-width="2" class="text-emerald-400" />
                                <X v-else :size="14" :stroke-width="2" class="text-zinc-600" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button type="button" class="text-xs text-zinc-500 hover:text-zinc-100 transition-colors" @click="startEdit(game)">
                                        <Pencil :size="13" :stroke-width="1.75" />
                                    </button>
                                    <button type="button" class="text-xs text-zinc-500 hover:text-red-400 transition-colors" @click="destroy(game)">
                                        <Trash2 :size="13" :stroke-width="1.75" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!games.length">
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-600 text-sm">No games configured yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </AdminLayout>
</template>
