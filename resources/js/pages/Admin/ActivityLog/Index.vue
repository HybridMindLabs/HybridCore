<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Activity, Search, X } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import EmptyState from '@/components/UI/EmptyState.vue';
import Pagination from '@/components/UI/Pagination.vue';
import { ref, watch } from 'vue';

interface LogEntry {
    id: number;
    event: string;
    description: string;
    causer: { name: string; email: string } | null;
    created_at: string;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator {
    data: LogEntry[]; links: PageLink[]; total: number;
    current_page: number; last_page: number; per_page: number;
    from: number | null; to: number | null;
}
interface Filters { search: string; category: string }

const props = defineProps<{ logs: Paginator; filters: Filters }>();

// Event metadata: label + color. Must stay in sync with every activity()->log()
// call site — see ActivityLogController::CATEGORIES for the matching backend map.
const EVENT_META: Record<string, { label: string; color: string }> = {
    'user.created':              { label: 'User Created',          color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
    'user.updated':               { label: 'User Updated',          color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
    'user.deleted':                { label: 'User Deleted',          color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'user.bulk_action':             { label: 'User Bulk Action',      color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
    'user.note_added':               { label: 'Admin Note Added',      color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'user.note_deleted':               { label: 'Admin Note Deleted',    color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'user.unlocked':                     { label: 'Login Unlocked',        color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'user.session_revoked':                { label: 'Session Revoked',       color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'user.impersonate_start':                { label: 'Impersonation Started', color: 'bg-amber-500/15 text-amber-400 border-amber-500/25' },
    'user.impersonate_stop':                   { label: 'Impersonation Stopped', color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'roles.created':                             { label: 'Role Created',          color: 'bg-violet-500/15 text-violet-400 border-violet-500/25' },
    'roles.updated':                               { label: 'Role Updated',          color: 'bg-violet-500/15 text-violet-400 border-violet-500/25' },
    'roles.deleted':                                 { label: 'Role Deleted',          color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'rules.created':                                   { label: 'Rule Created',          color: 'bg-violet-500/15 text-violet-400 border-violet-500/25' },
    'rules.updated':                                     { label: 'Rule Updated',          color: 'bg-violet-500/15 text-violet-400 border-violet-500/25' },
    'rules.deleted':                                       { label: 'Rule Deleted',          color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'page.created':                                          { label: 'Page Created',          color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'page.updated':                                            { label: 'Page Updated',          color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'page.published':                                            { label: 'Page Published',        color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'page.unpublished':                                            { label: 'Page Unpublished',      color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'page.deleted':                                                  { label: 'Page Deleted',          color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'legal.created':                                                   { label: 'Legal Page Created',    color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'legal.updated':                                                     { label: 'Legal Page Updated',    color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'legal.deleted':                                                       { label: 'Legal Page Deleted',    color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'news.article.created':                                                  { label: 'Article Created',       color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'news.article.updated':                                                    { label: 'Article Updated',       color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'news.article.published':                                                    { label: 'Article Published',     color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'news.article.deleted':                                                        { label: 'Article Deleted',       color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'news.article.bulk_action':                                                      { label: 'Article Bulk Action',   color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'news.category.created':                                                           { label: 'News Category Created', color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'news.category.updated':                                                             { label: 'News Category Updated', color: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/25' },
    'news.category.deleted':                                                               { label: 'News Category Deleted', color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'news.comment.deleted':                                                                  { label: 'News Comment Deleted',  color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'news.comment.bulk_deleted':                                                               { label: 'Comments Bulk Deleted', color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'server.bridge_token_issued':                                                                { label: 'Bridge Token Issued',   color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'server.bridge_token_revoked':                                                                 { label: 'Bridge Token Revoked',  color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'server.command_cancelled':                                                                      { label: 'Command Cancelled',     color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'servers.review.deleted':                                                                          { label: 'Review Deleted',        color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'webhook.created':                                                                                   { label: 'Webhook Created',       color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'webhook.updated':                                                                                     { label: 'Webhook Updated',       color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'webhook.secret_regenerated':                                                                            { label: 'Webhook Secret Reset',  color: 'bg-amber-500/15 text-amber-400 border-amber-500/25' },
    'webhook.delivery_retried':                                                                                { label: 'Delivery Retried',      color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
    'webhook.deleted':                                                                                           { label: 'Webhook Deleted',       color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'service_account.created':                                                                                     { label: 'API Token Created',     color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'service_account.token_issued':                                                                                  { label: 'API Token Issued',      color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'service_account.token_rotated':                                                                                   { label: 'API Token Rotated',     color: 'bg-amber-500/15 text-amber-400 border-amber-500/25' },
    'service_account.token_revoked':                                                                                     { label: 'API Token Revoked',     color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'service_account.deleted':                                                                                             { label: 'API Account Deleted',   color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'extension.enabled':                                                                                                     { label: 'Extension On',          color: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/25' },
    'extension.disabled':                                                                                                      { label: 'Extension Off',         color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'extension.uninstalled':                                                                                                     { label: 'Extension Uninstalled', color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'extension.updated':                                                                                                         { label: 'Extension Updated',     color: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/25' },
    'extensions.synced':                                                                                                         { label: 'Extensions Synced',     color: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/25' },
    'extensions.imported':                                                                                                       { label: 'Extension Imported',    color: 'bg-indigo-500/15 text-indigo-400 border-indigo-500/25' },
    'theme.activated':                                                                                                           { label: 'Theme Activated',       color: 'bg-pink-500/15 text-pink-400 border-pink-500/25' },
    'theme.deactivated':                                                                                                          { label: 'Theme Deactivated',     color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'themes.synced':                                                                                                              { label: 'Themes Synced',         color: 'bg-pink-500/15 text-pink-400 border-pink-500/25' },
    'report.resolved':                                                                                                           { label: 'Report Resolved',       color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'report.content_deleted':                                                                                                    { label: 'Reported Content Deleted', color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'backup.database':                                                                                                           { label: 'Database Backup',       color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
    'backup.scheduled':                                                                                                          { label: 'Scheduled Backup',      color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
    'backup.scheduled-failed':                                                                                                   { label: 'Scheduled Backup Failed', color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'backup.generated':                                                                                                          { label: 'Full Backup Generated', color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
    'backup.restored':                                                                                                           { label: 'Backup Restored',       color: 'bg-amber-500/15 text-amber-400 border-amber-500/25' },
    'backup.schedule-updated':                                                                                                   { label: 'Backup Schedule Updated', color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
    'backup.settings-exported':                                                                                                  { label: 'Settings Exported',     color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'backup.extensions-exported':                                                                                                { label: 'Extensions Exported',   color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'backup.themes-exported':                                                                                                    { label: 'Themes Exported',       color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'backup.content-exported':                                                                                                   { label: 'Content Exported',      color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'trash.article_restored':                                                                                                    { label: 'Article Restored',      color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'trash.comment_restored':                                                                                                    { label: 'Comment Restored',      color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'trash.article_purged':                                                                                                      { label: 'Article Purged',        color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'trash.comment_purged':                                                                                                      { label: 'Comment Purged',        color: 'bg-red-500/15 text-red-400 border-red-500/25' },
    'settings.updated':                                                                                                          { label: 'Settings Updated',      color: 'bg-amber-500/15 text-amber-400 border-amber-500/25' },
    'system.cache-cleared':                                                                                                      { label: 'Cache Cleared',         color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'system.config-cleared':                                                                                                     { label: 'Config Cache Cleared',  color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'system.route-cleared':                                                                                                      { label: 'Route Cache Cleared',   color: 'bg-zinc-800 text-zinc-400 border-zinc-700' },
    'system.maintenance-enabled':                                                                                                { label: 'Maintenance Enabled',   color: 'bg-amber-500/15 text-amber-400 border-amber-500/25' },
    'system.maintenance-disabled':                                                                                               { label: 'Maintenance Disabled',  color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/25' },
    'system.updated':                                                                                                            { label: 'System Updated',        color: 'bg-blue-500/15 text-blue-400 border-blue-500/25' },
};

// Fallback for anything not in the map above — humanize instead of showing the raw key.
function humanize(event: string): string {
    return event.split(/[._-]/).map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}
function eventMeta(event: string) {
    return EVENT_META[event] ?? { label: humanize(event), color: 'bg-zinc-800 text-zinc-500 border-zinc-700' };
}

// Must mirror ActivityLogController::CATEGORIES.
const CATEGORIES = [
    { key: 'users', label: 'Users' },
    { key: 'roles', label: 'Roles' },
    { key: 'rules', label: 'Rules' },
    { key: 'pages', label: 'Pages' },
    { key: 'legal', label: 'Legal' },
    { key: 'news', label: 'News' },
    { key: 'servers', label: 'Servers' },
    { key: 'webhooks', label: 'Webhooks' },
    { key: 'tokens', label: 'API Tokens' },
    { key: 'extensions', label: 'Extensions' },
    { key: 'themes', label: 'Themes' },
    { key: 'reports', label: 'Reports' },
    { key: 'backups', label: 'Backups' },
    { key: 'trash', label: 'Trash' },
    { key: 'system', label: 'System' },
];

const search = ref(props.filters.search);
const category = ref(props.filters.category);
const searching = ref(false);

function applyFilters() {
    searching.value = true;
    router.get(route('admin.activity-log.index'), {
        search: search.value || undefined,
        category: category.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true, onFinish: () => { searching.value = false; } });
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
});
watch(category, applyFilters);

function clearFilters() {
    search.value = '';
    category.value = '';
}

function causerInitials(name: string): string {
    return name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('');
}
</script>

<template>
    <Head title="Activity Log" />
    <AdminLayout title="Activity Log">

        <PageHeader
            title="Activity Log"
            :description="`${logs.total.toLocaleString()} recorded action${logs.total !== 1 ? 's' : ''}`"
            :icon="Activity"
        />

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <div class="relative flex-1 max-w-xs">
                <Search :size="13" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600 pointer-events-none" :stroke-width="1.75" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search events, descriptions, admins…"
                    class="w-full bg-[#111113] border border-zinc-800/70 rounded-lg pl-8 pr-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                />
            </div>

            <select
                v-model="category"
                class="bg-[#111113] border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 focus:outline-none focus:border-blue-500"
            >
                <option value="">All categories</option>
                <option v-for="cat in CATEGORIES" :key="cat.key" :value="cat.key">{{ cat.label }}</option>
            </select>

            <button
                v-if="category || search"
                type="button"
                class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs text-zinc-600 hover:text-zinc-300 transition-colors self-center"
                @click="clearFilters"
            >
                <X :size="11" :stroke-width="2" /> Clear
            </button>
        </div>

        <!-- Table -->
        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden transition-opacity" :class="searching ? 'opacity-50' : ''">
            <EmptyState
                v-if="logs.data.length === 0"
                :icon="Activity"
                :title="search || category ? 'No matching entries' : 'No activity yet'"
                :description="search || category ? 'Try adjusting the filters.' : 'Admin actions will be recorded here as they happen.'"
            />

            <table v-else class="w-full text-sm">
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
                        v-for="(log, i) in logs.data"
                        :key="log.id"
                        class="hc-hero-in border-b border-zinc-800/40 last:border-0 hover:bg-zinc-900/30 transition-colors"
                        :style="{ animationDelay: `${Math.min(i, 12) * 20}ms` }"
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
                </tbody>
            </table>

            <Pagination :paginator="logs" />
        </div>

    </AdminLayout>
</template>
