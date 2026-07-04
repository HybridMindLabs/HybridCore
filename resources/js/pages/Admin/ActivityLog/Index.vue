<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, Search, User, Settings, Server, Shield, Puzzle, Palette, X } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { ref, computed } from 'vue';

interface LogEntry {
    id: number;
    event: string;
    description: string;
    causer: { name: string; email: string } | null;
    created_at: string;
}

const props = defineProps<{ logs: LogEntry[] }>();

// Event metadata: label + color classes
const EVENT_META: Record<string, { label: string; color: string }> = {
    'user.created':           { label: 'User Created',        color: 'bg-blue-500/15 text-blue-400 border-blue-500/25'       },
    'user.updated':           { label: 'User Updated',        color: 'bg-blue-500/15 text-blue-400 border-blue-500/25'       },
    'user.deleted':           { label: 'User Deleted',        color: 'bg-red-500/15 text-red-400 border-red-500/25'          },
    'user.banned':            { label: 'User Banned',         color: 'bg-red-500/15 text-red-400 border-red-500/25'          },
    'user.unbanned':          { label: 'User Unbanned',       color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'user.bulk_action':       { label: 'Bulk Action',         color: 'bg-blue-500/15 text-blue-400 border-blue-500/25'       },
    'roles.created':          { label: 'Role Created',        color: 'bg-violet-500/15 text-violet-400 border-violet-500/25' },
    'roles.updated':          { label: 'Role Updated',        color: 'bg-violet-500/15 text-violet-400 border-violet-500/25' },
    'roles.deleted':          { label: 'Role Deleted',        color: 'bg-red-500/15 text-red-400 border-red-500/25'          },
    'settings.updated':       { label: 'Settings Updated',    color: 'bg-amber-500/15 text-amber-400 border-amber-500/25'    },
    'page.created':           { label: 'Page Created',        color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25'       },
    'page.updated':           { label: 'Page Updated',        color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25'       },
    'page.published':         { label: 'Page Published',      color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'page.unpublished':       { label: 'Page Unpublished',    color: 'bg-zinc-700 text-zinc-400 border-zinc-600'             },
    'page.deleted':           { label: 'Page Deleted',        color: 'bg-red-500/15 text-red-400 border-red-500/25'          },
    'server.created':         { label: 'Server Added',        color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'server.updated':         { label: 'Server Updated',      color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'server.deleted':         { label: 'Server Deleted',      color: 'bg-red-500/15 text-red-400 border-red-500/25'          },
    'extension.enabled':      { label: 'Extension On',        color: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/25' },
    'extension.disabled':     { label: 'Extension Off',       color: 'bg-zinc-700 text-zinc-400 border-zinc-600'             },
    'extensions.synced':      { label: 'Extensions Synced',   color: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/25' },
    'theme.activated':        { label: 'Theme Activated',     color: 'bg-pink-500/15 text-pink-400 border-pink-500/25'       },
    'theme.deactivated':      { label: 'Theme Deactivated',   color: 'bg-zinc-700 text-zinc-400 border-zinc-600'             },
    'themes.synced':          { label: 'Themes Synced',       color: 'bg-pink-500/15 text-pink-400 border-pink-500/25'       },
};

function eventMeta(event: string) {
    return EVENT_META[event] ?? { label: event, color: 'bg-zinc-800 text-zinc-500 border-zinc-700' };
}

// Category groups for filter chips
const CATEGORIES = [
    { key: 'user',      label: 'Users',      icon: User,     prefix: 'user.'      },
    { key: 'roles',     label: 'Roles',      icon: Shield,   prefix: 'roles.'     },
    { key: 'settings',  label: 'Settings',   icon: Settings, prefix: 'settings.'  },
    { key: 'page',      label: 'Pages',      icon: Activity, prefix: 'page.'      },
    { key: 'server',    label: 'Servers',    icon: Server,   prefix: 'server.'    },
    { key: 'extension', label: 'Extensions', icon: Puzzle,   prefix: 'extension.' },
    { key: 'theme',     label: 'Themes',     icon: Palette,  prefix: 'theme.'     },
] as const;

const search = ref('');
const activeCategory = ref<string | null>(null);

const filtered = computed(() => {
    let result = props.logs;
    if (activeCategory.value) {
        const cat = CATEGORIES.find((c) => c.key === activeCategory.value);
        if (cat) result = result.filter((l) => l.event.startsWith(cat.prefix) || l.event.startsWith(cat.key + 's.'));
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        result = result.filter((l) =>
            l.description.toLowerCase().includes(q) ||
            l.event.toLowerCase().includes(q) ||
            (l.causer?.name.toLowerCase().includes(q) ?? false),
        );
    }
    return result;
});

function causerInitials(name: string): string {
    return name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('');
}
</script>

<template>
    <Head title="Activity Log" />
    <AdminLayout title="Activity Log">

        <PageHeader
            title="Activity Log"
            description="Recent admin actions. Showing last 200 entries."
            :icon="Activity"
        />

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <!-- Search -->
            <div class="relative flex-1 max-w-xs">
                <Search :size="13" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600 pointer-events-none" :stroke-width="1.75" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search events…"
                    class="w-full bg-[#111113] border border-zinc-800/70 rounded-lg pl-8 pr-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                />
            </div>

            <!-- Category chips -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <button
                    v-for="cat in CATEGORIES"
                    :key="cat.key"
                    type="button"
                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium border transition-colors"
                    :class="activeCategory === cat.key
                        ? 'bg-blue-500/15 text-blue-400 border-blue-500/30'
                        : 'bg-zinc-900/60 text-zinc-500 border-zinc-800/70 hover:text-zinc-300 hover:border-zinc-700'"
                    @click="activeCategory = activeCategory === cat.key ? null : cat.key"
                >
                    <component :is="cat.icon" :size="11" :stroke-width="1.75" />
                    {{ cat.label }}
                </button>
                <button
                    v-if="activeCategory || search"
                    type="button"
                    class="flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs text-zinc-600 hover:text-zinc-300 transition-colors"
                    @click="activeCategory = null; search = ''"
                >
                    <X :size="11" :stroke-width="2" /> Clear
                </button>
            </div>

            <!-- Count -->
            <span class="ml-auto text-xs text-zinc-600 self-center shrink-0">
                {{ filtered.length }} / {{ logs.length }}
            </span>
        </div>

        <!-- Table -->
        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70">
                        <th class="text-left text-zinc-600 text-xs font-semibold uppercase tracking-wide px-4 py-3">Event</th>
                        <th class="text-left text-zinc-600 text-xs font-semibold uppercase tracking-wide px-4 py-3 hidden sm:table-cell">Description</th>
                        <th class="text-left text-zinc-600 text-xs font-semibold uppercase tracking-wide px-4 py-3 hidden md:table-cell">By</th>
                        <th class="text-left text-zinc-600 text-xs font-semibold uppercase tracking-wide px-4 py-3">When</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="log in filtered"
                        :key="log.id"
                        class="border-b border-zinc-800/40 last:border-0 hover:bg-zinc-900/30 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded border"
                                :class="eventMeta(log.event).color"
                            >
                                {{ eventMeta(log.event).label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-zinc-400 text-sm hidden sm:table-cell max-w-xs truncate">
                            {{ log.description }}
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            <div v-if="log.causer" class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-zinc-800 text-zinc-400 text-[10px] font-bold flex items-center justify-center shrink-0">
                                    {{ causerInitials(log.causer.name) }}
                                </span>
                                <span class="text-zinc-400 text-xs truncate max-w-[120px]">{{ log.causer.name }}</span>
                            </div>
                            <span v-else class="text-zinc-700 text-xs">System</span>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 text-xs font-mono whitespace-nowrap">
                            {{ log.created_at }}
                        </td>
                    </tr>

                    <tr v-if="filtered.length === 0">
                        <td colspan="4" class="px-4 py-16 text-center">
                            <Activity :size="28" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                            <p class="text-zinc-500 text-sm">No matching entries.</p>
                            <p class="text-zinc-700 text-xs mt-1">Try adjusting the filters.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </AdminLayout>
</template>
