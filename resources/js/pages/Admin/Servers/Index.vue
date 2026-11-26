<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, ChevronDown, Gamepad2, Plus, RefreshCw, Search, Server, Trash2, Users, X, XCircle } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ListControls from '@/components/Admin/ListControls.vue';
import SortHeader from '@/components/Admin/SortHeader.vue';
import AddServerForm from '@/components/Admin/Servers/AddServerForm.vue';
import BridgeTokenDialog from '@/components/Admin/Servers/BridgeTokenDialog.vue';
import EditServerRow from '@/components/Admin/Servers/EditServerRow.vue';
import ServerCommandLog from '@/components/Admin/Servers/ServerCommandLog.vue';
import ServerRow from '@/components/Admin/Servers/ServerRow.vue';
import type { AdminGame, AdminServer } from '@/components/Admin/Servers/serverForm';
import EmptyState from '@/components/UI/EmptyState.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import Pagination from '@/components/UI/Pagination.vue';
import { useClickOutside } from '@/composables/useClickOutside';
import { confirm } from '@/composables/useConfirm';
import { useSelection } from '@/composables/useSelection';

interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    servers: {
        data: AdminServer[]; links: PageLink[]; current_page: number; last_page: number;
        total: number; per_page: number; from: number | null; to: number | null;
    };
    games: AdminGame[];
    filters: { search: string | null; game_id: string | null; sort: string | null; dir: string };
    stats: { total: number; online: number; players: number };
}>();

// ── Filters ──────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '');
const gameId = ref(props.filters.game_id ?? '');
const searching = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    router.get(
        route('admin.servers.index'),
        {
            search: search.value || undefined,
            game_id: gameId.value || undefined,
            sort: props.filters.sort ?? undefined,
            dir: props.filters.sort ? props.filters.dir : undefined,
            per_page: new URLSearchParams(window.location.search).get('per_page') || undefined,
        },
        {
            preserveState: true, replace: true,
            onStart: () => { searching.value = true; },
            onFinish: () => { searching.value = false; },
        },
    );
}

// Typing used to fire a request per keystroke; wait for a short pause instead.
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});
watch(gameId, applyFilters);
onUnmounted(() => { if (searchTimer) clearTimeout(searchTimer); });

const sortParams = computed(() => ({ search: props.filters.search, game_id: props.filters.game_id }));

// ── Rows ─────────────────────────────────────────────────────────
const showAdd = ref(false);
const editing = ref<number | null>(null);
const expandedCommands = ref<number | null>(null);

function toggleCommands(id: number) {
    expandedCommands.value = expandedCommands.value === id ? null : id;
}

// ── Bridge token (one-time flash) ────────────────────────────────
const page = usePage<{ flash: { bridge_token?: string | null } }>();
const bridgeToken = computed(() => page.props.flash?.bridge_token ?? null);

function dismissToken() {
    page.props.flash.bridge_token = null;
}

// ── Bulk actions ─────────────────────────────────────────────────
type BulkAction = 'activate' | 'deactivate' | 'refresh' | 'delete';
const { selected, allSelected, toggleAll, toggleOne } = useSelection(() => props.servers.data.map((s) => s.id));
const bulkMenuOpen = ref(false);
const bulkPending = ref(false);
const bulkMenu = ref<HTMLElement | null>(null);
useClickOutside(bulkMenu, () => { bulkMenuOpen.value = false; });

const bulkLabels: Record<BulkAction, string> = {
    activate: 'Activate', deactivate: 'Deactivate', refresh: 'Query now', delete: 'Permanently delete',
};

async function runBulk(action: BulkAction) {
    bulkMenuOpen.value = false;
    if (selected.value.length === 0) return;
    if (!await confirm(`${bulkLabels[action]} ${selected.value.length} selected server(s)?`)) return;
    bulkPending.value = true;
    router.post(route('admin.servers.bulk'), { action, server_ids: selected.value }, {
        preserveScroll: true,
        onFinish: () => { bulkPending.value = false; selected.value = []; },
    });
}

const filtering = computed(() => !!(search.value || gameId.value));
</script>

<template>
    <Head title="Servers" />
    <AdminLayout title="Servers">

        <PageHeader title="Game Servers" description="Manage and monitor all game servers." :icon="Server">
            <template #actions>
                <Link :href="route('admin.servers.games')"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 border border-zinc-800/70 transition-colors">
                    <Gamepad2 :size="14" :stroke-width="1.75" /> Games
                </Link>
                <button type="button"
                    class="flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                    :aria-expanded="showAdd"
                    @click="showAdd = !showAdd">
                    <component :is="showAdd ? X : Plus" :size="14" :stroke-width="2" />
                    {{ showAdd ? 'Cancel' : 'Add Server' }}
                </button>
            </template>
        </PageHeader>

        <ListControls :per-page="servers.per_page" class="mb-3" />

        <!-- Stats — every server matching the current filters, not just this page. -->
        <div v-if="stats.total > 0" class="grid grid-cols-3 gap-4 mb-5">
            <div v-for="(card, i) in [
                    { icon: Server, value: stats.total, label: 'Total servers', tone: 'text-zinc-100', iconTone: 'text-zinc-500' },
                    { icon: CheckCircle2, value: stats.online, label: 'Online now', tone: 'text-emerald-400', iconTone: 'text-emerald-400' },
                    { icon: Users, value: stats.players, label: 'Players online', tone: 'text-blue-400', iconTone: 'text-blue-400' },
                ]" :key="card.label"
                class="hc-hero-in bg-admin-surface border border-zinc-800/70 rounded-xl px-4 py-3 flex items-center gap-3"
                :style="{ animationDelay: i * 40 + 'ms' }">
                <component :is="card.icon" :size="16" :stroke-width="1.5" class="shrink-0" :class="card.iconTone" aria-hidden="true" />
                <div>
                    <p class="text-lg font-bold leading-none" :class="card.tone">{{ card.value }}</p>
                    <p class="text-zinc-500 text-xs mt-0.5">{{ card.label }}</p>
                </div>
            </div>
        </div>

        <AddServerForm v-if="showAdd" :games="games" @close="showAdd = false" />

        <!-- Filters -->
        <div class="flex items-center gap-3 mb-4">
            <div class="relative flex-1 max-w-xs">
                <Search :size="13" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none" :stroke-width="1.75" aria-hidden="true" />
                <input v-model="search" type="search" placeholder="Search by IP or name…" aria-label="Search servers"
                    class="w-full bg-admin-surface border border-zinc-800/70 rounded-lg pl-8 pr-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20" />
            </div>
            <select v-model="gameId" aria-label="Filter by game"
                class="bg-admin-surface border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:border-blue-500">
                <option value="">All games</option>
                <option v-for="g in games" :key="g.id" :value="String(g.id)">{{ g.name }}</option>
            </select>

            <div v-if="selected.length > 0" ref="bulkMenu" class="relative">
                <button type="button"
                    class="flex items-center gap-1.5 border border-zinc-700 bg-zinc-800 text-zinc-200 text-sm font-semibold rounded-lg px-3 py-2 hover:bg-zinc-700 transition-colors"
                    :disabled="bulkPending" :aria-expanded="bulkMenuOpen" aria-haspopup="menu"
                    @click.stop="bulkMenuOpen = !bulkMenuOpen">
                    <span>{{ selected.length }} selected</span>
                    <ChevronDown :size="12" :stroke-width="2" :class="bulkMenuOpen ? 'rotate-180' : ''" class="transition-transform" />
                </button>
                <div v-if="bulkMenuOpen" role="menu" class="absolute left-0 top-full mt-1 w-44 bg-zinc-900 border border-zinc-800 rounded-lg shadow-xl py-1 z-50">
                    <button type="button" role="menuitem" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('activate')">
                        <CheckCircle2 :size="13" :stroke-width="1.75" class="text-emerald-400" /> Activate
                    </button>
                    <button type="button" role="menuitem" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('deactivate')">
                        <XCircle :size="13" :stroke-width="1.75" class="text-zinc-500" /> Deactivate
                    </button>
                    <button type="button" role="menuitem" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('refresh')">
                        <RefreshCw :size="13" :stroke-width="1.75" class="text-blue-400" /> Query now
                    </button>
                    <div class="border-t border-zinc-800 my-1" />
                    <button type="button" role="menuitem" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors" @click="runBulk('delete')">
                        <Trash2 :size="13" :stroke-width="1.75" /> Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden transition-opacity"
            :class="searching ? 'opacity-50 pointer-events-none' : ''">
            <table class="admin-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="w-8">
                            <input type="checkbox" :checked="allSelected" aria-label="Select all servers on this page"
                                class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer" @change="toggleAll" />
                        </th>
                        <th><SortHeader label="Server" column="name" :sort="filters.sort" :dir="filters.dir" route-name="admin.servers.index" :params="sortParams" /></th>
                        <th class="hidden sm:table-cell">Game</th>
                        <th>Status</th>
                        <th class="hidden md:table-cell"><SortHeader label="Players" column="players" :sort="filters.sort" :dir="filters.dir" route-name="admin.servers.index" :params="sortParams" /></th>
                        <th class="hidden lg:table-cell">Queried</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="server in servers.data" :key="server.id">
                        <EditServerRow v-if="editing === server.id" :server="server" :games="games" @done="editing = null" />
                        <ServerRow v-else :server="server"
                            :selected="selected.includes(server.id)" :commands-open="expandedCommands === server.id"
                            @toggle-select="toggleOne(server.id)" @toggle-commands="toggleCommands(server.id)" @edit="editing = server.id" />
                        <ServerCommandLog v-if="expandedCommands === server.id" :server="server" />
                    </template>

                    <tr v-if="!servers.data.length">
                        <td colspan="7">
                            <EmptyState :icon="Server"
                                :title="filtering ? 'No matches' : 'No servers yet'"
                                :description="filtering ? 'Try adjusting the search or game filter.' : 'Add your first game server to start monitoring it.'" />
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination :paginator="servers" />
        </div>

        <BridgeTokenDialog v-if="bridgeToken" :token="bridgeToken" @close="dismissToken" />
    </AdminLayout>
</template>
