<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Users, UserPlus, Pencil, Trash2, Search, X, ChevronDown, Loader2,
    ShieldBan, BadgeCheck, UserX, Download, ShieldCheck, Ban, CheckCircle2,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Badge from '@/components/UI/Badge.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import EmptyState from '@/components/UI/EmptyState.vue';
import Tooltip from '@/components/UI/Tooltip.vue';
import Pagination from '@/components/UI/Pagination.vue';
import { ref, watch, computed } from 'vue';

interface Role { name: string; slug: string; color: string; icon: string }
interface UserRow {
    id: number;
    name: string;
    username: string | null;
    email: string;
    avatar: string | null;
    is_admin: boolean;
    banned: boolean;
    verified: boolean;
    role: Role | null;
    roles_count: number;
    created_at: string;
    last_seen_at: string | null;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator {
    data: UserRow[]; links: PageLink[]; current_page: number; last_page: number;
    total: number; per_page: number; from: number | null; to: number | null;
}
interface SharedProps { auth: { user: { id: number } | null } }

const props = defineProps<{
    users: Paginator;
    filters: { search: string };
    stats: { total: number; active: number; banned: number; admins: number; unverified: number };
}>();
const page = usePage<SharedProps>();

const search = ref(props.filters.search);
const searching = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.users.index'), value ? { search: value } : {}, {
            preserveState: true, preserveScroll: true, replace: true,
            onStart: () => { searching.value = true; },
            onFinish: () => { searching.value = false; },
        });
    }, 350);
});
function clearSearch() { search.value = ''; }

function deleteUser(user: UserRow) {
    if (!confirm(`Delete user "${user.name}"? This cannot be undone.`)) return;
    router.delete(route('admin.users.destroy', user.id));
}

// ── Bulk actions ────────────────────────────────────────────
const selected = ref<number[]>([]);
const bulkMenuOpen = ref(false);
const bulkPending = ref(false);

const allSelected = computed(() =>
    props.users.data.length > 0 &&
    props.users.data.every((u) => selected.value.includes(u.id)),
);

function toggleAll() {
    if (allSelected.value) {
        selected.value = [];
    } else {
        selected.value = props.users.data
            .filter((u) => u.id !== page.props.auth?.user?.id)
            .map((u) => u.id);
    }
}

function toggleOne(id: number) {
    if (selected.value.includes(id)) {
        selected.value = selected.value.filter((x) => x !== id);
    } else {
        selected.value = [...selected.value, id];
    }
}

function runBulk(action: 'ban' | 'unban' | 'verify' | 'delete') {
    bulkMenuOpen.value = false;
    if (selected.value.length === 0) return;
    const label = { ban: 'ban', unban: 'unban', verify: 'verify', delete: 'permanently delete' }[action];
    if (!confirm(`${label} ${selected.value.length} selected user(s)?`)) return;
    bulkPending.value = true;
    router.post(route('admin.users.bulk'), { action, user_ids: selected.value }, {
        onFinish: () => { bulkPending.value = false; selected.value = []; },
    });
}

function userInitials(name: string) {
    return name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('') || '?';
}
</script>

<template>
    <Head title="Users" />
    <AdminLayout title="Users">

        <PageHeader title="Users" description="Manage roles, access, and account status for all members." :icon="Users">
            <template #actions>
                <Tooltip text="Export current search results to CSV">
                    <a
                        :href="route('admin.users.export') + (filters.search ? '?search=' + encodeURIComponent(filters.search) : '')"
                        class="flex items-center gap-1.5 border border-zinc-700 bg-zinc-800/60 text-zinc-300 font-medium rounded-lg px-3 py-2 text-sm hover:bg-zinc-700 transition-colors"
                    >
                        <Download :size="14" :stroke-width="1.75" /> Export CSV
                    </a>
                </Tooltip>
                <Link
                    :href="route('admin.users.create')"
                    class="flex items-center gap-1.5 bg-blue-500 text-white font-semibold rounded-lg px-3 py-2 text-sm hover:bg-blue-400 transition-colors"
                >
                    <UserPlus :size="14" :stroke-width="2" /> New User
                </Link>
            </template>
        </PageHeader>

        <!-- Stats bar -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3">
                <p class="text-zinc-600 text-[11px] font-medium uppercase tracking-wider mb-0.5">Total</p>
                <p class="text-zinc-100 text-xl font-bold tabular-nums">{{ stats.total.toLocaleString() }}</p>
            </div>
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3">
                <p class="text-zinc-600 text-[11px] font-medium uppercase tracking-wider mb-0.5">Active</p>
                <p class="text-emerald-400 text-xl font-bold tabular-nums">{{ stats.active.toLocaleString() }}</p>
            </div>
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3">
                <p class="text-zinc-600 text-[11px] font-medium uppercase tracking-wider mb-0.5">Banned</p>
                <p class="text-red-400 text-xl font-bold tabular-nums">{{ stats.banned.toLocaleString() }}</p>
            </div>
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3">
                <p class="text-zinc-600 text-[11px] font-medium uppercase tracking-wider mb-0.5">Admins</p>
                <p class="text-blue-400 text-xl font-bold tabular-nums">{{ stats.admins.toLocaleString() }}</p>
            </div>
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3">
                <p class="text-zinc-600 text-[11px] font-medium uppercase tracking-wider mb-0.5">Unverified</p>
                <p class="text-amber-400 text-xl font-bold tabular-nums">{{ stats.unverified.toLocaleString() }}</p>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="flex items-center gap-3 mb-4 flex-wrap">
            <!-- Search -->
            <div class="relative flex-1 min-w-[200px] max-w-sm">
                <Loader2 v-if="searching" :size="14" :stroke-width="1.75" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none animate-spin" />
                <Search v-else :size="14" :stroke-width="1.75" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600 pointer-events-none" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name, username, or email…"
                    class="w-full bg-[#111113] border border-zinc-800/70 text-zinc-100 rounded-lg pl-9 pr-9 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                />
                <button v-if="search" type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-600 hover:text-zinc-400" @click="clearSearch">
                    <X :size="14" :stroke-width="1.75" />
                </button>
            </div>

            <!-- Bulk actions -->
            <div v-if="selected.length > 0" class="relative">
                <button
                    type="button"
                    class="flex items-center gap-1.5 border border-zinc-700 bg-zinc-800 text-zinc-200 text-sm font-semibold rounded-lg px-3 py-2 hover:bg-zinc-700 transition-colors"
                    @click.stop="bulkMenuOpen = !bulkMenuOpen"
                >
                    <span>{{ selected.length }} selected</span>
                    <ChevronDown :size="12" :stroke-width="2" :class="bulkMenuOpen ? 'rotate-180' : ''" class="transition-transform" />
                </button>
                <div v-if="bulkMenuOpen" class="absolute left-0 top-full mt-1 w-44 bg-zinc-900 border border-zinc-800 rounded-lg shadow-xl py-1 z-50">
                    <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('ban')">
                        <ShieldBan :size="13" :stroke-width="1.75" class="text-red-400" /> Ban users
                    </button>
                    <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('unban')">
                        <UserX :size="13" :stroke-width="1.75" class="text-emerald-400" /> Unban users
                    </button>
                    <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('verify')">
                        <BadgeCheck :size="13" :stroke-width="1.75" class="text-blue-400" /> Verify emails
                    </button>
                    <div class="border-t border-zinc-800 my-1" />
                    <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors" @click="runBulk('delete')">
                        <Trash2 :size="13" :stroke-width="1.75" /> Delete users
                    </button>
                </div>
            </div>

            <p v-if="users.total > 0" class="ml-auto text-zinc-600 text-xs hidden sm:block">
                {{ users.from }}–{{ users.to }} of {{ users.total }} users
            </p>
        </div>

        <div :class="searching ? 'opacity-50 pointer-events-none transition-opacity' : 'transition-opacity'">
        <EmptyState v-if="users.data.length === 0 && !filters.search" :icon="Users" title="No users yet" description="Create the first user account to get started.">
            <template #action>
                <Link :href="route('admin.users.create')" class="flex items-center gap-1.5 bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-400 transition-colors">
                    <UserPlus :size="14" :stroke-width="2" /> New User
                </Link>
            </template>
        </EmptyState>

        <EmptyState v-else-if="users.data.length === 0" :icon="Search" title="No matches" :description="`No users match &quot;${filters.search}&quot;. Try a different name or email.`" />

        <div v-else class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70 bg-[#0d0d0f]">
                        <th class="px-4 py-3 w-8">
                            <input type="checkbox" :checked="allSelected" class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer" @change="toggleAll" />
                        </th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wider px-4 py-3">User</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wider px-4 py-3 hidden sm:table-cell">
                            <Tooltip text="Primary role is shown. A user may have multiple roles.">
                                <span class="cursor-help border-b border-dotted border-zinc-700">Role</span>
                            </Tooltip>
                        </th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Last seen</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wider px-4 py-3 hidden md:table-cell">Joined</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wider px-4 py-3">Status</th>
                        <th class="px-4 py-3 w-20" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/40">
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="hover:bg-zinc-900/30 transition-colors"
                        :class="selected.includes(user.id) ? 'bg-blue-500/[0.04]' : ''"
                    >
                        <!-- Checkbox -->
                        <td class="px-4 py-3 w-8">
                            <input
                                v-if="user.id !== page.props.auth?.user?.id"
                                type="checkbox"
                                :checked="selected.includes(user.id)"
                                class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer"
                                @change="toggleOne(user.id)"
                            />
                        </td>

                        <!-- User -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <!-- Avatar -->
                                <div class="w-8 h-8 rounded-full shrink-0 overflow-hidden border border-zinc-800">
                                    <img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="w-full h-full object-cover" />
                                    <div
                                        v-else
                                        class="w-full h-full flex items-center justify-center text-xs font-semibold"
                                        :style="user.role ? { background: user.role.color + '22', color: user.role.color } : { background: '#27272a', color: '#71717a' }"
                                    >
                                        {{ userInitials(user.name) }}
                                    </div>
                                </div>
                                <!-- Name + username/email -->
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <Link
                                            :href="route('admin.users.show', user.id)"
                                            class="text-zinc-100 font-medium text-sm truncate hover:text-blue-400 transition-colors"
                                        >{{ user.name }}</Link>
                                        <Tooltip v-if="user.verified" text="Email verified">
                                            <CheckCircle2 :size="12" :stroke-width="2" class="text-emerald-400 shrink-0" />
                                        </Tooltip>
                                    </div>
                                    <p class="text-zinc-600 text-xs truncate">
                                        <span v-if="user.username" class="text-zinc-500">@{{ user.username }} · </span>{{ user.email }}
                                    </p>
                                </div>
                            </div>
                        </td>

                        <!-- Role -->
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span
                                v-if="user.role"
                                class="inline-flex items-center gap-1.5 text-xs px-2 py-0.5 rounded-full font-medium"
                                :style="{ background: user.role.color + '22', color: user.role.color }"
                            >
                                {{ user.role.name }}
                                <span v-if="user.roles_count > 1" class="opacity-60">+{{ user.roles_count - 1 }}</span>
                            </span>
                            <span v-else class="text-zinc-700 text-xs">—</span>
                        </td>

                        <!-- Last seen -->
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <span class="text-zinc-500 text-xs">{{ user.last_seen_at ?? '—' }}</span>
                        </td>

                        <!-- Joined -->
                        <td class="px-4 py-3 hidden md:table-cell">
                            <span class="text-zinc-500 text-xs">{{ user.created_at }}</span>
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <Tooltip v-if="user.is_admin" text="Has admin panel access">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-400">
                                        <ShieldCheck :size="10" :stroke-width="2" />Admin
                                    </span>
                                </Tooltip>
                                <Tooltip v-if="user.banned" text="Banned — cannot sign in">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-red-500/15 text-red-400">
                                        <Ban :size="10" :stroke-width="2" />Banned
                                    </span>
                                </Tooltip>
                                <span
                                    v-if="!user.banned && !user.is_admin"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" />Active
                                </span>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 justify-end">
                                <Tooltip :text="`Edit ${user.name}`">
                                    <Link
                                        :href="route('admin.users.edit', user.id)"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-600 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                                    >
                                        <Pencil :size="13" :stroke-width="1.75" />
                                    </Link>
                                </Tooltip>
                                <Tooltip v-if="user.id !== page.props.auth?.user?.id" :text="`Delete ${user.name}`">
                                    <button
                                        type="button"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                                        @click="deleteUser(user)"
                                    >
                                        <Trash2 :size="13" :stroke-width="1.75" />
                                    </button>
                                </Tooltip>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :paginator="users" />
        </div>
        </div>

    </AdminLayout>
</template>
