<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Trash2, Plus, Copy, CheckCircle2, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface TokenRow {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
    is_expired: boolean;
    expires_soon: boolean;
}
interface AccountRow {
    id: number;
    name: string;
    created_at: string;
    tokens: TokenRow[];
}
interface AbilityDef { label: string; group: string }

const props = defineProps<{
    accounts: AccountRow[];
    available_abilities: Record<string, AbilityDef>;
}>();

const page = usePage<{ flash: { plain_token?: string | null } }>();
const plainToken = computed(() => page.props.flash?.plain_token ?? null);
const copied = ref(false);

async function copyToken() {
    if (!plainToken.value) return;
    await navigator.clipboard.writeText(plainToken.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function dismissToken() {
    router.reload({ only: [] });
    page.props.flash.plain_token = null;
}

const groupedAbilities = computed(() => {
    const groups: Record<string, { key: string; label: string }[]> = {};
    for (const [key, def] of Object.entries(props.available_abilities)) {
        (groups[def.group] ??= []).push({ key, label: def.label });
    }
    return groups;
});

// ── Create account + first token ────────────────────────────────
const createForm = useForm<{ name: string; abilities: string[] }>({ name: '', abilities: [] });

function submitCreate() {
    createForm.post(route('admin.api-tokens.store'), { preserveScroll: true, onSuccess: () => createForm.reset() });
}

// ── Issue another token on an existing account ──────────────────
const issuingFor = ref<number | null>(null);
const issueForm = useForm<{ name: string; abilities: string[] }>({ name: '', abilities: [] });

function startIssue(accountId: number) {
    issuingFor.value = accountId;
    issueForm.reset();
}

function submitIssue(accountId: number) {
    issueForm.post(route('admin.api-tokens.tokens.store', accountId), {
        preserveScroll: true,
        onSuccess: () => { issuingFor.value = null; issueForm.reset(); },
    });
}

function revokeToken(tokenId: number) {
    if (!confirm('Revoke this token? Anything using it stops working immediately.')) return;
    router.delete(route('admin.api-tokens.tokens.destroy', tokenId), { preserveScroll: true });
}

function rotateToken(tokenId: number) {
    if (!confirm('Rotate this token? The old credential stops working immediately — you\'ll get a new one to copy.')) return;
    router.post(route('admin.api-tokens.tokens.rotate', tokenId), {}, { preserveScroll: true });
}

function destroyAccount(account: AccountRow) {
    if (!confirm(`Delete "${account.name}" and all ${account.tokens.length} of its token(s)? This cannot be undone.`)) return;
    router.delete(route('admin.api-tokens.destroy', account.id));
}
</script>

<template>
    <Head title="API Tokens" />
    <AdminLayout title="API Tokens">
        <PageHeader title="API Tokens" description="Admin-issued credentials for external integrations." :icon="KeyRound" />

        <!-- Plaintext-once banner -->
        <div
            v-if="plainToken"
            class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4 mb-5 flex items-start gap-3"
        >
            <CheckCircle2 :size="16" :stroke-width="2" class="text-emerald-400 mt-0.5 shrink-0" />
            <div class="flex-1 min-w-0">
                <p class="text-emerald-300 text-xs font-semibold mb-1.5">
                    Copy this token now — it will not be shown again.
                </p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 min-w-0 truncate bg-zinc-950 border border-zinc-800 rounded-lg px-3 py-2 text-xs font-mono text-zinc-200">{{ plainToken }}</code>
                    <button type="button" class="p-2 rounded-lg border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" @click="copyToken">
                        <CheckCircle2 v-if="copied" :size="14" :stroke-width="2" class="text-emerald-400" />
                        <Copy v-else :size="14" :stroke-width="2" />
                    </button>
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" @click="dismissToken">
                        Dismiss
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            <!-- Accounts list -->
            <div class="flex flex-col gap-4">
                <div v-if="accounts.length === 0" class="bg-[#111113] border border-zinc-800/70 rounded-xl p-6 text-center text-zinc-500 text-sm">
                    No service accounts yet — create one to issue your first token.
                </div>

                <div v-for="account in accounts" :key="account.id" class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-zinc-100 text-sm font-semibold">{{ account.name }}</h3>
                            <p class="text-zinc-600 text-[11px]">Created {{ account.created_at }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" @click="startIssue(account.id)">
                                <Plus :size="11" :stroke-width="2" />
                                New token
                            </button>
                            <button type="button" class="p-1.5 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors" @click="destroyAccount(account)">
                                <Trash2 :size="12" :stroke-width="2" />
                            </button>
                        </div>
                    </div>

                    <!-- Issue-token inline form -->
                    <form v-if="issuingFor === account.id" class="bg-zinc-900/60 border border-zinc-800 rounded-lg p-3 mb-3 flex flex-col gap-2" @submit.prevent="submitIssue(account.id)">
                        <input v-model="issueForm.name" type="text" placeholder="Token name" class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50" />
                        <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                            <template v-for="(defs, group) in groupedAbilities" :key="group">
                                <label v-for="a in defs" :key="a.key" class="flex items-center gap-1.5 text-[11px] text-zinc-400">
                                    <input v-model="issueForm.abilities" type="checkbox" :value="a.key" class="rounded border-zinc-700" />
                                    {{ a.label }}
                                </label>
                            </template>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" :disabled="issueForm.processing" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-40 transition-colors">Issue</button>
                            <button type="button" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold border border-zinc-700 text-zinc-400" @click="issuingFor = null">Cancel</button>
                        </div>
                    </form>

                    <div v-if="account.tokens.length === 0" class="text-zinc-600 text-xs">No tokens on this account.</div>
                    <div v-else class="flex flex-col gap-1.5">
                        <div v-for="token in account.tokens" :key="token.id" class="flex items-center justify-between px-3 py-2 rounded-lg bg-zinc-900/40 border border-zinc-800/60">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-zinc-300 text-xs font-medium truncate">{{ token.name }}</p>
                                    <span v-if="token.is_expired" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-500/15 text-red-400 shrink-0">Expired</span>
                                    <span v-else-if="token.expires_soon" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/15 text-amber-400 shrink-0">Expires soon</span>
                                </div>
                                <p class="text-zinc-600 text-[10px] font-mono truncate">{{ token.abilities.join(', ') }}</p>
                                <p class="text-zinc-600 text-[10px]">
                                    {{ token.last_used_at ? `Last used ${token.last_used_at}` : 'Never used' }}
                                    <span v-if="token.expires_at"> · Expires {{ token.expires_at }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" class="p-1.5 rounded-lg border border-zinc-700 text-zinc-400 hover:text-zinc-100 transition-colors" title="Rotate — revoke and reissue" @click="rotateToken(token.id)">
                                    <RefreshCw :size="12" :stroke-width="2" />
                                </button>
                                <button type="button" class="p-1.5 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors" title="Revoke" @click="revokeToken(token.id)">
                                    <Trash2 :size="12" :stroke-width="2" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create new account -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <h3 class="text-zinc-100 text-sm font-semibold mb-3">New Service Account</h3>
                <form class="flex flex-col gap-3" @submit.prevent="submitCreate">
                    <div>
                        <input v-model="createForm.name" type="text" placeholder="e.g. Discord Bot" class="w-full bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50" />
                        <p v-if="createForm.errors.name" class="text-red-400 text-[11px] mt-1">{{ createForm.errors.name }}</p>
                    </div>

                    <div v-for="(defs, group) in groupedAbilities" :key="group">
                        <p class="text-zinc-500 text-[10px] font-semibold uppercase tracking-wide mb-1.5">{{ group }}</p>
                        <div class="flex flex-col gap-1">
                            <label v-for="a in defs" :key="a.key" class="flex items-center gap-2 text-xs text-zinc-400">
                                <input v-model="createForm.abilities" type="checkbox" :value="a.key" class="rounded border-zinc-700" />
                                {{ a.label }}
                            </label>
                        </div>
                    </div>
                    <p v-if="createForm.errors.abilities" class="text-red-400 text-[11px]">{{ createForm.errors.abilities }}</p>

                    <button type="submit" :disabled="createForm.processing" class="px-3 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-40 transition-colors">
                        Create &amp; issue token
                    </button>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
