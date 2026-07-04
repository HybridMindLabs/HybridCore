<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, KeyRound, UserCog, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import RoleMultiSelect from '@/components/Admin/RoleMultiSelect.vue';
import { ROLE_ICONS } from '@/constants/icons';

interface Role { id: number; name: string; slug: string; color: string; icon: string }

const props = defineProps<{ roles: Role[] }>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
    primary_role: null as string | null,
    is_admin: false,
});

const initials = computed(() =>
    form.name.trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase() ?? '').join('') || '?',
);

const primaryRole = computed(() => props.roles.find((role) => role.slug === form.primary_role) ?? null);

function submit() {
    form.post(route('admin.users.store'));
}

const inputClass = 'bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20';
const errorInputClass = 'border-red-500';
</script>

<template>
    <Head title="Create User" />
    <AdminLayout title="Create User">

        <div class="flex items-center gap-3 mb-6">
            <Link
                :href="route('admin.users.index')"
                class="flex items-center gap-1.5 text-zinc-400 hover:text-zinc-100 text-sm transition-colors"
            >
                <ChevronLeft :size="14" :stroke-width="1.75" />
                Users
            </Link>
            <span class="text-zinc-600 text-sm">/</span>
            <span class="text-zinc-100 text-sm">New User</span>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            <!-- Main column -->
            <div class="flex flex-col gap-5 min-w-0">

                <!-- Profile -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-6">
                    <h2 class="text-zinc-100 text-base font-semibold mb-1">Profile</h2>
                    <p class="text-zinc-500 text-xs mb-5">Basic account details visible across the platform.</p>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Full name</label>
                            <input v-model="form.name" type="text" autocomplete="name" :class="[inputClass, form.errors.name ? errorInputClass : '']" />
                            <p v-if="form.errors.name" class="text-red-400 text-xs">{{ form.errors.name }}</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Email address</label>
                            <input v-model="form.email" type="email" autocomplete="email" :class="[inputClass, form.errors.email ? errorInputClass : '']" />
                            <p v-if="form.errors.email" class="text-red-400 text-xs">{{ form.errors.email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-6">
                    <div class="flex items-center gap-2 mb-1">
                        <KeyRound :size="15" :stroke-width="1.75" class="text-zinc-600" />
                        <h2 class="text-zinc-100 text-base font-semibold">Password</h2>
                    </div>
                    <p class="text-zinc-500 text-xs mb-5">Set an initial password. The user can change it later from their account.</p>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Password</label>
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                :class="[inputClass, form.errors.password ? errorInputClass : '']"
                            />
                            <p v-if="form.errors.password" class="text-red-400 text-xs">{{ form.errors.password }}</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Confirm password</label>
                            <input v-model="form.password_confirmation" type="password" autocomplete="new-password" :class="inputClass" />
                        </div>
                    </div>
                </div>

                <!-- Roles & access -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-6">
                    <div class="flex items-center gap-2 mb-1">
                        <UserCog :size="15" :stroke-width="1.75" class="text-zinc-600" />
                        <h2 class="text-zinc-100 text-base font-semibold">Roles &amp; Access</h2>
                    </div>
                    <p class="text-zinc-500 text-xs mb-4">
                        Assign one or more roles. The role marked <span class="text-blue-400">★ Primary</span> is the one shown next to the user's name.
                    </p>

                    <RoleMultiSelect
                        v-model="form.roles"
                        :primary="form.primary_role"
                        :roles="roles"
                        @update:primary="(v) => (form.primary_role = v)"
                    />
                    <p v-if="form.errors.roles" class="text-red-400 text-xs mt-2">{{ form.errors.roles }}</p>

                    <div class="flex flex-col gap-3 mt-5 pt-5 border-t border-zinc-800/70">
                        <label class="flex items-start gap-3 cursor-pointer select-none">
                            <input v-model="form.is_admin" type="checkbox" class="w-4 h-4 mt-0.5 rounded border border-zinc-800/70 bg-zinc-900/60 accent-blue-500" />
                            <span>
                                <span class="text-zinc-100 text-sm block">Admin panel access</span>
                                <span class="text-zinc-500 text-xs">Lets this user sign in to the admin panel, in addition to their role permissions.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-blue-500 text-[#0a0f1a] font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-blue-600 transition-colors disabled:opacity-60"
                    >
                        Create user
                    </button>
                    <Link :href="route('admin.users.index')" class="text-zinc-400 hover:text-zinc-100 text-sm transition-colors">
                        Cancel
                    </Link>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="flex flex-col gap-5">
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-6 text-center">
                    <div
                        class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-xl font-bold mb-3"
                        :style="primaryRole
                            ? { backgroundColor: primaryRole.color + '1a', color: primaryRole.color }
                            : { backgroundColor: '#1a2236', color: '#64748b' }"
                    >
                        {{ initials }}
                    </div>
                    <p class="text-zinc-100 text-sm font-semibold truncate">{{ form.name || 'New user' }}</p>
                    <p class="text-zinc-500 text-xs truncate mb-4">{{ form.email || 'No email yet' }}</p>

                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                        <span
                            v-if="primaryRole"
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                            :style="{ backgroundColor: primaryRole.color + '1a', color: primaryRole.color }"
                        >
                            <component :is="ROLE_ICONS[primaryRole.icon]" :size="11" :stroke-width="2" />
                            {{ primaryRole.name }}
                        </span>
                        <span v-else class="text-zinc-600 text-xs">No role assigned yet</span>
                    </div>
                </div>

                <div class="bg-[#0d1f3c]/40 border border-[#3b82f6]/20 rounded-xl p-5 flex items-start gap-3">
                    <UserPlus :size="15" :stroke-width="1.75" class="text-[#3b82f6] mt-0.5 shrink-0" />
                    <p class="text-zinc-400 text-xs leading-relaxed">
                        The new user will be created immediately. They can sign in right away using the email and password set here.
                    </p>
                </div>
            </div>
        </form>

    </AdminLayout>
</template>
