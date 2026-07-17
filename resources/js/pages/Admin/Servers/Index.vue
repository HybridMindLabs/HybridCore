<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Server, Plus, RefreshCw, Search, Gamepad2,
    CheckCircle2, XCircle, Minus, Pencil, Trash2,
    X, ChevronDown, ChevronUp, Users, Plug, Copy, Check,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { ref, watch, computed } from 'vue';

interface GameData { id: number; name: string; color: string; icon: string }
interface ServerStatus { is_online: boolean; failure_reason: string | null; players_online: number; players_max: number; map: string | null }
interface ServerRow {
    id: number; ip: string; port: number; query_port: number | null; address: string; name: string | null;
    country_code: string | null; tags: string[]; is_active: boolean;
    last_queried_at: string | null;
    bridge: { enabled: boolean; last_seen: string | null; online: boolean };
    game: { id: number; name: string; color: string; icon: string };
    status: ServerStatus | null;
}

const props = defineProps<{
    servers: { data: ServerRow[]; meta: any; links: any };
    games: GameData[];
    filters: { search: string | null; game_id: string | null };
}>();

// Filters
const search = ref(props.filters.search ?? '');
const gameId = ref(props.filters.game_id ?? '');
const searching = ref(false);

watch([search, gameId], () => {
    router.get(
        route('admin.servers.index'),
        { search: search.value || undefined, game_id: gameId.value || undefined },
        {
            preserveState: true, replace: true,
            onStart: () => { searching.value = true; },
            onFinish: () => { searching.value = false; },
        },
    );
});

// ── Bridge (game-server connection) ─────────────────────────
const page = usePage<{ flash: { bridge_token?: string | null } }>();
const bridgeToken = computed(() => page.props.flash?.bridge_token ?? null);
const tokenCopied = ref(false);

function issueBridgeToken(server: ServerRow) {
    const message = server.bridge.enabled
        ? `Rotate the bridge token for "${server.address}"? The current token stops working immediately.`
        : `Generate a bridge token for "${server.address}"? The in-game plugin uses it to pull commands from the site.`;
    if (!confirm(message)) return;
    router.post(route('admin.servers.bridge.issue', server.id), {}, { preserveScroll: true });
}

function revokeBridgeToken(server: ServerRow) {
    if (!confirm(`Revoke the bridge token for "${server.address}"? The game server will be disconnected.`)) return;
    router.delete(route('admin.servers.bridge.revoke', server.id), { preserveScroll: true });
}

async function copyToken() {
    if (!bridgeToken.value) return;
    await navigator.clipboard.writeText(bridgeToken.value);
    tokenCopied.value = true;
    setTimeout(() => (tokenCopied.value = false), 2000);
}

function dismissToken() {
    router.reload({ only: [] }); // clears the one-time flash on next visit
    page.props.flash.bridge_token = null;
}

// ── Bulk actions ────────────────────────────────────────────
type BulkAction = 'activate' | 'deactivate' | 'refresh' | 'delete';
const selected = ref<number[]>([]);
const bulkMenuOpen = ref(false);
const bulkPending = ref(false);

const allSelected = computed(() =>
    props.servers.data.length > 0 &&
    props.servers.data.every((s) => selected.value.includes(s.id)),
);

function toggleAll() {
    selected.value = allSelected.value ? [] : props.servers.data.map((s) => s.id);
}

function toggleOne(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((x) => x !== id)
        : [...selected.value, id];
}

function runBulk(action: BulkAction) {
    bulkMenuOpen.value = false;
    if (selected.value.length === 0) return;
    const labels: Record<BulkAction, string> = {
        activate: 'activate', deactivate: 'deactivate', refresh: 'query now', delete: 'permanently delete',
    };
    if (!confirm(`${labels[action]} ${selected.value.length} selected server(s)?`)) return;
    bulkPending.value = true;
    router.post(route('admin.servers.bulk'), { action, server_ids: selected.value }, {
        onFinish: () => { bulkPending.value = false; selected.value = []; },
    });
}

// Add server form
const showAdd = ref(false);
const addForm = useForm({ game_id: '', address: '', query_port: '', tags: '' });
const addFormError = ref('');

function submitAdd() {
    addFormError.value = '';
    const parts = addForm.address.trim().split(':');
    if (parts.length !== 2 || !parts[0] || !parts[1] || isNaN(Number(parts[1]))) {
        addFormError.value = 'Use format IP:PORT — e.g. 192.168.1.1:27015';
        return;
    }
    addForm.transform((d) => ({
        game_id: d.game_id,
        ip: parts[0].trim(),
        port: Number(parts[1]),
        query_port: d.query_port ? Number(d.query_port) : null,
        tags: d.tags ? d.tags.split(',').map((t: string) => t.trim()).filter(Boolean) : [],
    })).post(route('admin.servers.store'), {
        onSuccess: () => { addForm.reset(); showAdd.value = false; },
    });
}

// Inline edit
const editing = ref<number | null>(null);
const editForm = useForm({ game_id: '', address: '', query_port: '', name: '', is_active: true, tags: '' });
const editFormError = ref('');

function startEdit(s: ServerRow) {
    editing.value = s.id;
    editFormError.value = '';
    editForm.game_id = String(s.game.id);
    editForm.address = s.address;
    editForm.query_port = s.query_port ? String(s.query_port) : '';
    editForm.name = s.name ?? '';
    editForm.is_active = s.is_active;
    editForm.tags = s.tags.join(', ');
}

function submitEdit(id: number) {
    editFormError.value = '';
    const parts = editForm.address.trim().split(':');
    if (parts.length !== 2 || !parts[0] || !parts[1] || isNaN(Number(parts[1]))) {
        editFormError.value = 'Use format IP:PORT — e.g. 192.168.1.1:27015';
        return;
    }
    editForm.transform((d) => ({
        game_id: d.game_id,
        ip: parts[0].trim(),
        port: Number(parts[1]),
        query_port: d.query_port ? Number(d.query_port) : null,
        name: d.name || null,
        is_active: d.is_active,
        tags: d.tags ? d.tags.split(',').map((t: string) => t.trim()).filter(Boolean) : [],
    })).put(route('admin.servers.update', id), {
        onSuccess: () => { editing.value = null; },
    });
}

function refresh(id: number) {
    router.post(route('admin.servers.refresh', id));
}

function destroy(s: ServerRow) {
    if (!confirm(`Delete server "${s.name ?? s.address}"?`)) return;
    router.delete(route('admin.servers.destroy', s.id));
}

const totalOnline = computed(() =>
    props.servers.data.filter((s) => s.status?.is_online).length,
);
const totalPlayers = computed(() =>
    props.servers.data.reduce((sum, s) => sum + (s.status?.players_online ?? 0), 0),
);

const inputClass = 'bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 w-full';
</script>

<template>
    <Head title="Servers" />
    <AdminLayout title="Servers">

        <PageHeader title="Game Servers" description="Manage and monitor all game servers." :icon="Server">
            <template #actions>
                <Link
                    :href="route('admin.servers.games')"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 border border-zinc-800/70 transition-colors"
                >
                    <Gamepad2 :size="14" :stroke-width="1.75" /> Games
                </Link>
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                    @click="showAdd = !showAdd"
                >
                    <component :is="showAdd ? X : Plus" :size="14" :stroke-width="2" />
                    {{ showAdd ? 'Cancel' : 'Add Server' }}
                </button>
            </template>
        </PageHeader>

        <!-- Stats bar -->
        <div v-if="servers.data.length" class="grid grid-cols-3 gap-4 mb-5">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3 flex items-center gap-3">
                <Server :size="16" :stroke-width="1.5" class="text-zinc-600 shrink-0" />
                <div>
                    <p class="text-zinc-100 text-lg font-bold leading-none">{{ servers.meta?.total ?? servers.data.length }}</p>
                    <p class="text-zinc-600 text-xs mt-0.5">Total servers</p>
                </div>
            </div>
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3 flex items-center gap-3">
                <CheckCircle2 :size="16" :stroke-width="1.5" class="text-emerald-400 shrink-0" />
                <div>
                    <p class="text-emerald-400 text-lg font-bold leading-none">{{ totalOnline }}</p>
                    <p class="text-zinc-600 text-xs mt-0.5">Online now</p>
                </div>
            </div>
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3 flex items-center gap-3">
                <Users :size="16" :stroke-width="1.5" class="text-blue-400 shrink-0" />
                <div>
                    <p class="text-blue-400 text-lg font-bold leading-none">{{ totalPlayers }}</p>
                    <p class="text-zinc-600 text-xs mt-0.5">Players online</p>
                </div>
            </div>
        </div>

        <!-- Add Server Panel -->
        <div v-if="showAdd" class="mb-5 bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-zinc-800/50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-zinc-100 flex items-center gap-2">
                    <Plus :size="14" :stroke-width="2" class="text-blue-400" /> Add New Server
                </h3>
                <button type="button" class="text-zinc-600 hover:text-zinc-300 transition-colors" @click="showAdd = false">
                    <X :size="14" :stroke-width="1.75" />
                </button>
            </div>
            <form class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" @submit.prevent="submitAdd">
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium">Game <span class="text-red-400">*</span></label>
                    <select v-model="addForm.game_id" :class="inputClass">
                        <option value="">Select game…</option>
                        <option v-for="g in games" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </select>
                    <p v-if="addForm.errors.game_id" class="text-red-400 text-xs">{{ addForm.errors.game_id }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium">
                        IP:PORT <span class="text-red-400">*</span>
                        <span class="text-zinc-700 font-normal ml-1">e.g. 192.168.1.1:27015</span>
                    </label>
                    <input
                        v-model="addForm.address"
                        type="text"
                        placeholder="192.168.1.1:27015"
                        :class="[inputClass, 'font-mono', addFormError ? 'border-red-500' : '']"
                    />
                    <p v-if="addFormError" class="text-red-400 text-xs">{{ addFormError }}</p>
                    <p v-else-if="addForm.errors.ip" class="text-red-400 text-xs">{{ addForm.errors.ip }}</p>
                    <p v-else-if="addForm.errors.port" class="text-red-400 text-xs">{{ addForm.errors.port }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium">
                        Query port
                        <span class="text-zinc-700 font-normal ml-1">only if different from the game port — Rust +2, ARK 27015</span>
                    </label>
                    <input v-model="addForm.query_port" type="number" placeholder="same as game port" :class="[inputClass, 'font-mono']" />
                    <p v-if="addForm.errors.query_port" class="text-red-400 text-xs">{{ addForm.errors.query_port }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium">Tags <span class="text-zinc-700 font-normal">comma separated</span></label>
                    <input v-model="addForm.tags" type="text" placeholder="competitive, ranked" :class="inputClass" />
                </div>
                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        :disabled="addForm.processing"
                        class="flex-1 bg-blue-500 text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-blue-400 transition-colors disabled:opacity-50"
                    >
                        {{ addForm.processing ? 'Adding…' : 'Add & Query' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-3 mb-4">
            <div class="relative flex-1 max-w-xs">
                <Search :size="13" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600 pointer-events-none" :stroke-width="1.75" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by IP or name…"
                    class="w-full bg-[#111113] border border-zinc-800/70 rounded-lg pl-8 pr-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                />
            </div>
            <select
                v-model="gameId"
                class="bg-[#111113] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:border-blue-500"
            >
                <option value="">All games</option>
                <option v-for="g in games" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>

            <!-- Bulk actions -->
            <div v-if="selected.length > 0" class="relative">
                <button
                    type="button"
                    class="flex items-center gap-1.5 border border-zinc-700 bg-zinc-800 text-zinc-200 text-sm font-semibold rounded-lg px-3 py-2 hover:bg-zinc-700 transition-colors"
                    @click.stop="bulkMenuOpen = !bulkMenuOpen"
                    :disabled="bulkPending"
                >
                    <span>{{ selected.length }} selected</span>
                    <ChevronDown :size="12" :stroke-width="2" :class="bulkMenuOpen ? 'rotate-180' : ''" class="transition-transform" />
                </button>
                <div v-if="bulkMenuOpen" class="absolute left-0 top-full mt-1 w-44 bg-zinc-900 border border-zinc-800 rounded-lg shadow-xl py-1 z-50">
                    <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('activate')">
                        <CheckCircle2 :size="13" :stroke-width="1.75" class="text-emerald-400" /> Activate
                    </button>
                    <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('deactivate')">
                        <XCircle :size="13" :stroke-width="1.75" class="text-zinc-500" /> Deactivate
                    </button>
                    <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('refresh')">
                        <RefreshCw :size="13" :stroke-width="1.75" class="text-blue-400" /> Query now
                    </button>
                    <div class="border-t border-zinc-800 my-1" />
                    <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors" @click="runBulk('delete')">
                        <Trash2 :size="13" :stroke-width="1.75" /> Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden transition-opacity"
            :class="searching ? 'opacity-50 pointer-events-none' : ''">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70">
                        <th class="px-4 py-3 w-8">
                            <input type="checkbox" :checked="allSelected" class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer" @change="toggleAll" />
                        </th>
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide">Server</th>
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide hidden sm:table-cell">Game</th>
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide">Status</th>
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide hidden md:table-cell">Players</th>
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide hidden lg:table-cell">Queried</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="server in servers.data" :key="server.id">

                        <!-- Edit row -->
                        <tr v-if="editing === server.id" class="border-b border-zinc-800/70 bg-zinc-900/40">
                            <td colspan="7" class="px-4 py-4">
                                <form class="flex flex-col gap-3" @submit.prevent="submitEdit(server.id)">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-zinc-600 text-[11px]">Game</label>
                                            <select v-model="editForm.game_id" :class="inputClass + ' !py-1.5 !text-xs'">
                                                <option v-for="g in games" :key="g.id" :value="g.id">{{ g.name }}</option>
                                            </select>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-zinc-600 text-[11px]">IP:PORT</label>
                                            <input
                                                v-model="editForm.address"
                                                type="text"
                                                placeholder="1.2.3.4:27015"
                                                :class="[inputClass, 'font-mono !py-1.5 !text-xs', editFormError ? 'border-red-500' : '']"
                                            />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-zinc-600 text-[11px]">Query port</label>
                                            <input v-model="editForm.query_port" type="number" placeholder="same as game" :class="inputClass + ' font-mono !py-1.5 !text-xs'" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-zinc-600 text-[11px]">Name override</label>
                                            <input v-model="editForm.name" type="text" placeholder="Optional display name" :class="inputClass + ' !py-1.5 !text-xs'" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-zinc-600 text-[11px]">Tags</label>
                                            <input v-model="editForm.tags" type="text" placeholder="tag1, tag2" :class="inputClass + ' !py-1.5 !text-xs'" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-zinc-600 text-[11px]">Active</label>
                                            <label class="flex items-center gap-2 cursor-pointer mt-1.5">
                                                <input v-model="editForm.is_active" type="checkbox" class="w-4 h-4 rounded accent-blue-500" />
                                                <span class="text-zinc-400 text-xs">Enabled</span>
                                            </label>
                                        </div>
                                    </div>
                                    <p v-if="editFormError" class="text-red-400 text-xs">{{ editFormError }}</p>
                                    <div class="flex items-center gap-2 justify-end pt-1 border-t border-zinc-800/50">
                                        <button
                                            type="button"
                                            class="px-3 py-1.5 text-xs text-zinc-500 hover:text-zinc-100 transition-colors"
                                            @click="editing = null"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="editForm.processing"
                                            class="px-4 py-1.5 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                                        >
                                            Save changes
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>

                        <!-- Normal row -->
                        <tr
                            v-else
                            class="border-b border-zinc-800/50 last:border-0 hover:bg-zinc-900/30 transition-colors"
                            :class="[!server.is_active ? 'opacity-40' : '', selected.includes(server.id) ? 'bg-blue-500/[0.04]' : '']"
                        >
                            <td class="px-4 py-3 w-8">
                                <input
                                    type="checkbox"
                                    :checked="selected.includes(server.id)"
                                    class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer"
                                    @change="toggleOne(server.id)"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-zinc-100 text-sm font-medium leading-tight">
                                    {{ server.name ?? server.address }}
                                </p>
                                <p class="text-zinc-600 text-xs font-mono mt-0.5">{{ server.address }}</p>
                                <div v-if="server.tags.length" class="flex flex-wrap gap-1 mt-1.5">
                                    <span
                                        v-for="tag in server.tags"
                                        :key="tag"
                                        class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800/80 text-zinc-500"
                                    >{{ tag }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden sm:table-cell">
                                <span
                                    class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full"
                                    :style="{ background: server.game.color + '1a', color: server.game.color }"
                                >
                                    {{ server.game.name }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="server.status">
                                    <div class="flex items-center gap-1.5">
                                        <CheckCircle2 v-if="server.status.is_online" :size="13" :stroke-width="2" class="text-emerald-400 shrink-0" />
                                        <XCircle v-else :size="13" :stroke-width="2" class="text-red-400 shrink-0" />
                                        <span class="text-xs font-medium" :class="server.status.is_online ? 'text-emerald-400' : 'text-red-400'">
                                            {{ server.status.is_online ? 'Online' : 'Offline' }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="!server.status.is_online && server.status.failure_reason"
                                        class="text-red-400/60 text-[11px] mt-0.5 max-w-[16rem] truncate"
                                        :title="server.status.failure_reason"
                                    >{{ server.status.failure_reason }}</p>
                                </div>
                                <div v-else class="flex items-center gap-1.5">
                                    <Minus :size="13" :stroke-width="2" class="text-zinc-700" />
                                    <span class="text-xs text-zinc-600">Not queried</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span v-if="server.status?.is_online" class="text-zinc-300 text-xs tabular-nums">
                                    {{ server.status.players_online }}<span class="text-zinc-600">/{{ server.status.players_max }}</span>
                                </span>
                                <span v-else class="text-zinc-700 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="text-zinc-600 text-xs">{{ server.last_queried_at ?? 'Never' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        type="button"
                                        :title="server.bridge.enabled
                                            ? `Bridge: ${server.bridge.online ? 'connected' : 'no recent heartbeat'}${server.bridge.last_seen ? ' · last seen ' + server.bridge.last_seen : ''} — click to rotate token, right-click to revoke`
                                            : 'Generate bridge token for the in-game plugin'"
                                        class="relative w-7 h-7 flex items-center justify-center rounded-lg transition-colors"
                                        :class="server.bridge.enabled
                                            ? (server.bridge.online ? 'text-emerald-400 hover:bg-emerald-500/10' : 'text-amber-400 hover:bg-amber-500/10')
                                            : 'text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800'"
                                        @click="issueBridgeToken(server)"
                                        @contextmenu.prevent="server.bridge.enabled && revokeBridgeToken(server)"
                                    >
                                        <Plug :size="13" :stroke-width="1.75" />
                                        <span
                                            v-if="server.bridge.enabled"
                                            class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full"
                                            :class="server.bridge.online ? 'bg-emerald-400' : 'bg-amber-400'"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        title="Refresh status"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-blue-400 hover:bg-blue-500/10 transition-colors"
                                        @click="refresh(server.id)"
                                    >
                                        <RefreshCw :size="13" :stroke-width="1.75" />
                                    </button>
                                    <button
                                        type="button"
                                        title="Edit server"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                                        @click="startEdit(server)"
                                    >
                                        <Pencil :size="13" :stroke-width="1.75" />
                                    </button>
                                    <button
                                        type="button"
                                        title="Delete server"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                                        @click="destroy(server)"
                                    >
                                        <Trash2 :size="13" :stroke-width="1.75" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="!servers.data.length">
                        <td colspan="7" class="px-4 py-16 text-center">
                            <Server :size="28" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                            <p class="text-zinc-500 text-sm">No servers found.</p>
                            <p class="text-zinc-700 text-xs mt-1">Try adjusting the filters or add a new server.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="servers.meta?.last_page > 1" class="flex items-center justify-center gap-1 mt-4">
            <Link
                v-for="link in servers.links"
                :key="link.label"
                :href="link.url ?? '#'"
                v-html="link.label"
                class="px-3 py-1.5 rounded-lg text-xs transition-colors"
                :class="link.active
                    ? 'bg-blue-500 text-white font-semibold'
                    : link.url
                        ? 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800'
                        : 'text-zinc-700 cursor-default'"
                :as="link.url ? 'a' : 'span'"
            />
        </div>

        <!-- One-time bridge token reveal -->
        <div v-if="bridgeToken" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg bg-[#111113] border border-zinc-800 rounded-2xl p-6">
                <h3 class="text-zinc-100 text-base font-bold flex items-center gap-2">
                    <Plug :size="16" :stroke-width="1.75" class="text-emerald-400" /> Bridge token generated
                </h3>
                <p class="text-zinc-500 text-xs mt-1 leading-relaxed">
                    Paste this token into the in-game plugin config. For security it is stored hashed —
                    <span class="text-amber-400 font-semibold">this is the only time it will be shown</span>.
                    If you lose it, generate a new one.
                </p>

                <div class="flex items-center gap-2 mt-4 bg-zinc-900/70 border border-zinc-800 rounded-lg px-3 py-2.5">
                    <code class="flex-1 text-[13px] text-emerald-300 font-mono break-all select-all">{{ bridgeToken }}</code>
                    <button
                        type="button"
                        class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-500 transition-colors"
                        title="Copy token"
                        @click="copyToken"
                    >
                        <component :is="tokenCopied ? Check : Copy" :size="13" :stroke-width="1.75" :class="tokenCopied ? 'text-emerald-400' : ''" />
                    </button>
                </div>

                <p class="text-zinc-600 text-[11px] mt-3 font-mono">
                    POST /api/bridge/poll · Authorization: Bearer &lt;token&gt;
                </p>

                <button
                    type="button"
                    class="mt-4 w-full bg-blue-500 text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-blue-400 transition-colors"
                    @click="dismissToken"
                >
                    I saved the token
                </button>
            </div>
        </div>

    </AdminLayout>
</template>
