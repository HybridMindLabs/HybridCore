<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Webhook, Trash2, Plus, Copy, CheckCircle2, RefreshCw, Send, ChevronDown, Redo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import EmptyState from '@/components/UI/EmptyState.vue';

interface DeliveryRow {
    id: number;
    event: string;
    success: boolean;
    response_code: number | null;
    error: string | null;
    created_at: string | null;
    retryable: boolean;
}
interface EndpointRow {
    id: number;
    name: string;
    url: string;
    events: string[];
    is_active: boolean;
    last_triggered_at: string | null;
    last_status: string | null;
    last_response_code: number | null;
    created_at: string;
    deliveries: DeliveryRow[];
}

const props = defineProps<{
    endpoints: EndpointRow[];
    available_events: string[];
}>();

const page = usePage<{ flash: { webhook_secret?: string | null } }>();
const webhookSecret = computed(() => page.props.flash?.webhook_secret ?? null);
const copied = ref(false);

async function copySecret() {
    if (!webhookSecret.value) return;
    await navigator.clipboard.writeText(webhookSecret.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function dismissSecret() {
    router.reload({ only: [] });
    page.props.flash.webhook_secret = null;
}

// ── Create endpoint ──────────────────────────────────────────────
const createForm = useForm<{ name: string; url: string; events: string[] }>({ name: '', url: '', events: [] });

function submitCreate() {
    createForm.post(route('admin.webhooks.store'), { preserveScroll: true, onSuccess: () => createForm.reset() });
}

// ── Edit endpoint ────────────────────────────────────────────────
const editingId = ref<number | null>(null);
const editForm = useForm<{ name: string; url: string; events: string[]; is_active: boolean }>({
    name: '',
    url: '',
    events: [],
    is_active: true,
});

function startEdit(endpoint: EndpointRow) {
    editingId.value = endpoint.id;
    editForm.name = endpoint.name;
    editForm.url = endpoint.url;
    editForm.events = [...endpoint.events];
    editForm.is_active = endpoint.is_active;
}

function submitEdit(id: number) {
    editForm.put(route('admin.webhooks.update', id), { preserveScroll: true, onSuccess: () => (editingId.value = null) });
}

function regenerateSecret(id: number) {
    if (!confirm('Regenerate this webhook\'s secret? The old one stops verifying immediately.')) return;
    router.post(route('admin.webhooks.regenerate-secret', id), {}, { preserveScroll: true });
}

function sendTest(id: number) {
    router.post(route('admin.webhooks.test', id), {}, { preserveScroll: true });
}

const retrying = ref<number | null>(null);

function retryDelivery(endpointId: number, deliveryId: number) {
    retrying.value = deliveryId;
    router.post(route('admin.webhooks.deliveries.retry', [endpointId, deliveryId]), {}, {
        preserveScroll: true,
        onFinish: () => (retrying.value = null),
    });
}

const expandedLog = ref<number | null>(null);

function toggleLog(id: number) {
    expandedLog.value = expandedLog.value === id ? null : id;
}

function destroyEndpoint(endpoint: EndpointRow) {
    if (!confirm(`Delete "${endpoint.name}"? This cannot be undone.`)) return;
    router.delete(route('admin.webhooks.destroy', endpoint.id));
}
</script>

<template>
    <Head title="Webhooks" />
    <AdminLayout title="Webhooks">
        <PageHeader title="Webhooks" description="Deliver core events to external endpoints over HTTP." :icon="Webhook" />

        <!-- Plaintext-once secret banner -->
        <div
            v-if="webhookSecret"
            class="hc-hero-in bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4 mb-5 flex items-start gap-3"
        >
            <CheckCircle2 :size="16" :stroke-width="2" class="text-emerald-400 mt-0.5 shrink-0" />
            <div class="flex-1 min-w-0">
                <p class="text-emerald-300 text-xs font-semibold mb-1.5">
                    Copy this secret now — it will not be shown again.
                </p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 min-w-0 truncate bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-xs font-mono text-zinc-200">{{ webhookSecret }}</code>
                    <button type="button" class="p-2 rounded-lg border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" @click="copySecret">
                        <CheckCircle2 v-if="copied" :size="14" :stroke-width="2" class="text-emerald-400" />
                        <Copy v-else :size="14" :stroke-width="2" />
                    </button>
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" @click="dismissSecret">
                        Dismiss
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            <!-- Endpoints list -->
            <div class="flex flex-col gap-4">
                <div v-if="endpoints.length === 0" class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl">
                    <EmptyState
                        title="No webhook endpoints yet"
                        description="Add one to start receiving events."
                        :icon="Webhook"
                    />
                </div>

                <div
                    v-for="(endpoint, i) in endpoints"
                    :key="endpoint.id"
                    class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl p-5"
                    :style="{ animationDelay: `${Math.min(i, 5) * 40}ms` }"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-zinc-100 text-sm font-semibold truncate">{{ endpoint.name }}</h3>
                                <span v-if="!endpoint.is_active" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-zinc-700/50 text-zinc-400 shrink-0">Inactive</span>
                                <span v-else-if="endpoint.last_status === 'failed'" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-500/15 text-red-400 shrink-0">Failing</span>
                            </div>
                            <p class="text-zinc-600 text-[11px] truncate">{{ endpoint.url }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" @click="sendTest(endpoint.id)">
                                <Send :size="11" :stroke-width="2" />
                                Send test
                            </button>
                            <button type="button" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" @click="startEdit(endpoint)">
                                Edit
                            </button>
                            <button type="button" class="p-1.5 rounded-lg border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" title="Regenerate secret" @click="regenerateSecret(endpoint.id)">
                                <RefreshCw :size="12" :stroke-width="2" />
                            </button>
                            <button type="button" class="p-1.5 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors" title="Delete" @click="destroyEndpoint(endpoint)">
                                <Trash2 :size="12" :stroke-width="2" />
                            </button>
                        </div>
                    </div>

                    <!-- Edit inline form -->
                    <form v-if="editingId === endpoint.id" class="bg-zinc-900/60 border border-zinc-800 rounded-lg p-3 mb-3 flex flex-col gap-2" @submit.prevent="submitEdit(endpoint.id)">
                        <input v-model="editForm.name" type="text" placeholder="Name" class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50" />
                        <input v-model="editForm.url" type="text" placeholder="https://example.com/hook" class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50" />
                        <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                            <label v-for="event in props.available_events" :key="event" class="flex items-center gap-1.5 text-[11px] text-zinc-400">
                                <input v-model="editForm.events" type="checkbox" :value="event" class="rounded border-zinc-700" />
                                {{ event }}
                            </label>
                        </div>
                        <label class="flex items-center gap-1.5 text-[11px] text-zinc-400">
                            <input v-model="editForm.is_active" type="checkbox" class="rounded border-zinc-700" />
                            Active
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" :disabled="editForm.processing" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-40 transition-colors">Save</button>
                            <button type="button" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold border border-zinc-700 text-zinc-400" @click="editingId = null">Cancel</button>
                        </div>
                    </form>

                    <p class="text-zinc-600 text-[10px] font-mono truncate">{{ endpoint.events.join(', ') }}</p>
                    <button type="button" class="flex items-center gap-1 text-zinc-600 text-[10px] hover:text-zinc-300 transition-colors" @click="toggleLog(endpoint.id)">
                        {{ endpoint.last_triggered_at ? `Last triggered ${endpoint.last_triggered_at}` : 'Never triggered' }}
                        <span v-if="endpoint.last_response_code"> · HTTP {{ endpoint.last_response_code }}</span>
                        <ChevronDown :size="10" :stroke-width="2" class="transition-transform" :class="{ 'rotate-180': expandedLog === endpoint.id }" />
                    </button>

                    <div v-if="expandedLog === endpoint.id" class="mt-2 flex flex-col gap-1">
                        <div v-if="endpoint.deliveries.length === 0" class="text-zinc-700 text-[10px]">No deliveries yet.</div>
                        <div
                            v-for="delivery in endpoint.deliveries"
                            :key="delivery.id"
                            class="flex items-center justify-between px-2 py-1 rounded bg-zinc-900/40 text-[10px]"
                        >
                            <span class="text-zinc-400 font-mono truncate">{{ delivery.event }}</span>
                            <span class="flex items-center gap-2 shrink-0">
                                <span v-if="delivery.response_code" class="text-zinc-600">HTTP {{ delivery.response_code }}</span>
                                <span :class="delivery.success ? 'text-emerald-400' : 'text-red-400'">{{ delivery.success ? 'OK' : 'Failed' }}</span>
                                <span class="text-zinc-700">{{ delivery.created_at }}</span>
                                <button
                                    v-if="delivery.retryable"
                                    type="button"
                                    :disabled="retrying !== null"
                                    title="Retry this delivery"
                                    class="flex items-center gap-1 text-zinc-500 hover:text-blue-400 transition-colors disabled:opacity-50"
                                    @click="retryDelivery(endpoint.id, delivery.id)"
                                >
                                    <Redo2 :size="10" :stroke-width="2" />
                                    {{ retrying === delivery.id ? '…' : 'Retry' }}
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create new endpoint -->
            <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl p-5" style="animation-delay: 40ms">
                <h3 class="text-zinc-100 text-sm font-semibold mb-3">New Webhook</h3>
                <form class="flex flex-col gap-3" @submit.prevent="submitCreate">
                    <div>
                        <input v-model="createForm.name" type="text" placeholder="e.g. Discord Relay" class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50" />
                        <p v-if="createForm.errors.name" class="text-red-400 text-[11px] mt-1">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <input v-model="createForm.url" type="text" placeholder="https://example.com/hook" class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50" />
                        <p v-if="createForm.errors.url" class="text-red-400 text-[11px] mt-1">{{ createForm.errors.url }}</p>
                    </div>

                    <div>
                        <p class="text-zinc-500 text-[10px] font-semibold uppercase tracking-wide mb-1.5">Events</p>
                        <div class="flex flex-col gap-1 max-h-48 overflow-y-auto">
                            <label v-for="event in available_events" :key="event" class="flex items-center gap-2 text-xs text-zinc-400">
                                <input v-model="createForm.events" type="checkbox" :value="event" class="rounded border-zinc-700" />
                                {{ event }}
                            </label>
                        </div>
                    </div>
                    <p v-if="createForm.errors.events" class="text-red-400 text-[11px]">{{ createForm.errors.events }}</p>

                    <button type="submit" :disabled="createForm.processing" class="px-3 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-40 transition-colors">
                        <Plus :size="12" :stroke-width="2" class="inline -mt-0.5 mr-1" />
                        Create webhook
                    </button>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
