<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ChevronLeft, ShieldAlert, Ban, Trash2, KeyRound, UserCog, IdCard,
    Calendar, Clock, CheckCircle2, XCircle, ShieldCheck, Hash,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import RoleMultiSelect from '@/components/Admin/RoleMultiSelect.vue';
import { ROLE_ICONS } from '@/constants/icons';

interface Role { id: number; name: string; slug: string; color: string; icon: string }

interface UserProp {
    id: number;
    name: string;
    username: string | null;
    email: string;
    avatar: string | null;
    is_admin: boolean;
    banned: boolean;
    verified: boolean;
    roles: string[];
    primary_role: string | null;
    created_at: string | null;
    last_seen_at: string | null;
}

const props = defineProps<{ user: UserProp; roles: Role[] }>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    roles: [...props.user.roles] as string[],
    primary_role: props.user.primary_role,
    is_admin: props.user.is_admin,
    banned: props.user.banned,
});

const tabs = [
    { key: 'profile', label: 'Profile',        icon: IdCard   },
    { key: 'security', label: 'Security',       icon: KeyRound },
    { key: 'access',   label: 'Roles & Access', icon: UserCog  },
] as const;

const activeTab = ref<typeof tabs[number]['key']>('profile');

const initials = computed(() =>
    form.name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase() ?? '').join('') || '?',
);

const primaryRole = computed(() => props.roles.find((role) => role.slug === form.primary_role) ?? null);

const hasTabError = (key: string) => {
    if (key === 'profile')  return Boolean(form.errors.name || form.errors.email);
    if (key === 'security') return Boolean(form.errors.password);
    if (key === 'access')   return Boolean(form.errors.roles);
    return false;
};

function submit() {
    form.put(route('admin.users.update', props.user.id));
}

function deleteUser() {
    if (!confirm(`Delete user "${props.user.name}"? This cannot be undone.`)) return;
    router.delete(route('admin.users.destroy', props.user.id));
}

function quickBan() {
    if (form.banned) {
        if (!confirm(`Unban "${props.user.name}"?`)) return;
        form.banned = false;
    } else {
        if (!confirm(`Ban "${props.user.name}"? They will be signed out immediately.`)) return;
        form.banned = true;
    }
    form.put(route('admin.users.update', props.user.id), { preserveScroll: true });
}

const inputClass = 'bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 w-full';
const errorInputClass = 'border-red-500';
</script>

<template>
    <Head :title="`Edit — ${user.name}`" />
    <AdminLayout title="Edit User">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 mb-6 text-sm">
            <Link :href="route('admin.users.index')" class="flex items-center gap-1.5 text-zinc-500 hover:text-zinc-100 transition-colors">
                <ChevronLeft :size="13" :stroke-width="1.75" /> Users
            </Link>
            <span class="text-zinc-700">/</span>
            <span class="text-zinc-300">{{ user.name }}</span>
            <span class="text-zinc-700 font-mono text-xs ml-1">#{{ user.id }}</span>
        </div>

        <form class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-5 items-start" @submit.prevent="submit">

            <!-- ── Main column ───────────────────────────────────── -->
            <div class="flex flex-col gap-5 min-w-0">

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">

                    <!-- Tabs -->
                    <div class="flex items-center gap-0.5 px-4 pt-3 border-b border-zinc-800/70">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="relative flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors rounded-t-lg"
                            :class="activeTab === tab.key
                                ? 'text-zinc-100'
                                : 'text-zinc-500 hover:text-zinc-300'"
                            @click="activeTab = tab.key"
                        >
                            <component :is="tab.icon" :size="14" :stroke-width="1.75" />
                            {{ tab.label }}
                            <span v-if="hasTabError(tab.key)" class="w-1.5 h-1.5 rounded-full bg-red-500 absolute top-2 right-2" />
                            <span v-if="activeTab === tab.key" class="absolute left-0 right-0 -bottom-px h-[2px] bg-blue-500 rounded-full" />
                        </button>
                    </div>

                    <div class="p-6">

                        <!-- ── Profile tab ──────────────────────── -->
                        <div v-show="activeTab === 'profile'">
                            <h2 class="text-zinc-100 text-base font-semibold mb-1">Profile</h2>
                            <p class="text-zinc-500 text-xs mb-5 leading-relaxed">
                                The display name and email used across the platform. Changing the email updates the sign-in address but not the password.
                            </p>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-zinc-400 text-xs font-semibold uppercase tracking-wider">Full name</label>
                                    <input v-model="form.name" type="text" :class="[inputClass, form.errors.name ? errorInputClass : '']" autocomplete="off" />
                                    <p v-if="form.errors.name" class="text-red-400 text-xs">{{ form.errors.name }}</p>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-zinc-400 text-xs font-semibold uppercase tracking-wider">Email address</label>
                                    <input v-model="form.email" type="email" :class="[inputClass, form.errors.email ? errorInputClass : '']" autocomplete="off" />
                                    <p v-if="form.errors.email" class="text-red-400 text-xs">{{ form.errors.email }}</p>
                                    <p class="text-zinc-600 text-xs">Used to sign in and receive notifications.</p>
                                </div>
                            </div>
                        </div>

                        <!-- ── Security tab ─────────────────────── -->
                        <div v-show="activeTab === 'security'">
                            <h2 class="text-zinc-100 text-base font-semibold mb-1">Password</h2>
                            <p class="text-zinc-500 text-xs mb-5 leading-relaxed">
                                Set a new password for this user. Leave both fields blank to keep their current password unchanged.
                            </p>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-zinc-400 text-xs font-semibold uppercase tracking-wider">New password</label>
                                    <input
                                        v-model="form.password"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="Min. 8 characters"
                                        :class="[inputClass, form.errors.password ? errorInputClass : '']"
                                    />
                                    <p v-if="form.errors.password" class="text-red-400 text-xs">{{ form.errors.password }}</p>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-zinc-400 text-xs font-semibold uppercase tracking-wider">Confirm password</label>
                                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat new password" :class="inputClass" />
                                </div>
                            </div>
                        </div>

                        <!-- ── Access tab ───────────────────────── -->
                        <div v-show="activeTab === 'access'">
                            <h2 class="text-zinc-100 text-base font-semibold mb-1">Roles &amp; Access</h2>
                            <p class="text-zinc-500 text-xs mb-4 leading-relaxed">
                                A user can hold multiple roles at once — their permissions are the union of all of them.
                                The <span class="text-blue-400 font-medium">★ Primary</span> role is cosmetic: it's the badge shown next to their name.
                            </p>

                            <RoleMultiSelect
                                v-model="form.roles"
                                :primary="form.primary_role"
                                :roles="roles"
                                @update:primary="(v) => (form.primary_role = v)"
                            />
                            <p v-if="form.errors.roles" class="text-red-400 text-xs mt-2">{{ form.errors.roles }}</p>

                            <div class="flex flex-col gap-3 mt-5 pt-5 border-t border-zinc-800/70">
                                <label class="flex items-start gap-3 cursor-pointer select-none group">
                                    <input v-model="form.is_admin" type="checkbox" class="w-4 h-4 mt-0.5 rounded border border-zinc-700 bg-zinc-900 accent-blue-500 shrink-0" />
                                    <span>
                                        <span class="text-zinc-100 text-sm block font-medium">Admin panel access</span>
                                        <span class="text-zinc-500 text-xs leading-relaxed">
                                            Allows this user to sign in at <code class="text-zinc-400 bg-zinc-800 px-1 py-0.5 rounded text-[11px]">/admin</code>.
                                            Their role permissions still govern what they can do inside.
                                        </span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer select-none group">
                                    <input v-model="form.banned" type="checkbox" class="w-4 h-4 mt-0.5 rounded border border-zinc-700 bg-zinc-900 accent-red-500 shrink-0" />
                                    <span>
                                        <span class="text-zinc-100 text-sm block font-medium">Banned</span>
                                        <span class="text-zinc-500 text-xs leading-relaxed">
                                            Signs the user out immediately and blocks all future sign-in attempts — on both the public site and admin panel — until unchecked.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Save / Cancel -->
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-blue-500 text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-blue-400 transition-colors disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </button>
                    <Link :href="route('admin.users.index')" class="text-zinc-500 hover:text-zinc-100 text-sm transition-colors">
                        Cancel
                    </Link>
                </div>
            </div>

            <!-- ── Sidebar ───────────────────────────────────────── -->
            <div class="flex flex-col gap-4">

                <!-- User card -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <!-- Avatar + name -->
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 rounded-xl shrink-0 overflow-hidden border border-zinc-800">
                            <img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="w-full h-full object-cover" />
                            <div
                                v-else
                                class="w-full h-full flex items-center justify-center text-base font-bold"
                                :style="primaryRole
                                    ? { background: primaryRole.color + '22', color: primaryRole.color }
                                    : { background: '#27272a', color: '#71717a' }"
                            >
                                {{ initials }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-zinc-100 text-sm font-semibold truncate">{{ form.name || 'Unnamed' }}</p>
                            <p v-if="user.username" class="text-zinc-500 text-xs truncate">@{{ user.username }}</p>
                            <p v-else class="text-zinc-600 text-xs truncate">{{ form.email }}</p>
                        </div>
                    </div>

                    <!-- Role + flags -->
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        <span
                            v-if="primaryRole"
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                            :style="{ background: primaryRole.color + '22', color: primaryRole.color }"
                        >
                            <component :is="ROLE_ICONS[primaryRole.icon]" :size="11" :stroke-width="2" />
                            {{ primaryRole.name }}
                        </span>
                        <span v-if="form.is_admin" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium bg-blue-500/15 text-blue-400">
                            <ShieldAlert :size="10" :stroke-width="2" /> Admin
                        </span>
                        <span v-if="form.banned" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium bg-red-500/15 text-red-400">
                            <Ban :size="10" :stroke-width="2" /> Banned
                        </span>
                    </div>

                    <!-- Meta info -->
                    <div class="flex flex-col gap-2 py-3 border-t border-zinc-800/50 text-xs">
                        <div class="flex items-center gap-2 text-zinc-500">
                            <Hash :size="12" :stroke-width="1.75" class="text-zinc-700 shrink-0" />
                            <span class="text-zinc-600">User ID</span>
                            <span class="ml-auto font-mono text-zinc-400">{{ user.id }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <Calendar :size="12" :stroke-width="1.75" class="text-zinc-700 shrink-0" />
                            <span class="text-zinc-600">Joined</span>
                            <span class="ml-auto text-zinc-400">{{ user.created_at ?? '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <Clock :size="12" :stroke-width="1.75" class="text-zinc-700 shrink-0" />
                            <span class="text-zinc-600">Last seen</span>
                            <span class="ml-auto text-zinc-400">{{ user.last_seen_at ?? 'Never' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <CheckCircle2 v-if="user.verified" :size="12" :stroke-width="1.75" class="text-emerald-400 shrink-0" />
                            <XCircle v-else :size="12" :stroke-width="1.75" class="text-amber-400 shrink-0" />
                            <span class="text-zinc-600">Email</span>
                            <span class="ml-auto" :class="user.verified ? 'text-emerald-400' : 'text-amber-400'">
                                {{ user.verified ? 'Verified' : 'Not verified' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <ShieldCheck :size="12" :stroke-width="1.75" class="text-zinc-700 shrink-0" />
                            <span class="text-zinc-600">Admin access</span>
                            <span class="ml-auto" :class="form.is_admin ? 'text-blue-400' : 'text-zinc-600'">
                                {{ form.is_admin ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>

                    <!-- Quick ban toggle -->
                    <div class="pt-3 border-t border-zinc-800/50">
                        <button
                            type="button"
                            class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition-colors"
                            :class="form.banned
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20'
                                : 'bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500/20'"
                            @click="quickBan"
                        >
                            <Ban :size="12" :stroke-width="1.75" />
                            {{ form.banned ? 'Unban this user' : 'Ban this user' }}
                        </button>
                    </div>
                </div>

                <!-- Danger zone -->
                <div class="bg-[#111113] border border-red-500/20 rounded-xl p-5">
                    <h3 class="text-red-400 text-xs font-semibold uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <Trash2 :size="13" :stroke-width="1.75" /> Danger Zone
                    </h3>
                    <p class="text-zinc-500 text-xs mb-3 leading-relaxed">
                        Permanently delete this user account and all associated data. This action cannot be undone.
                    </p>
                    <button
                        type="button"
                        class="w-full bg-red-500/10 text-red-400 border border-red-500/30 rounded-lg px-4 py-2 text-xs font-semibold hover:bg-red-500/20 transition-colors"
                        @click="deleteUser"
                    >
                        Delete account permanently
                    </button>
                </div>
            </div>

        </form>

    </AdminLayout>
</template>
