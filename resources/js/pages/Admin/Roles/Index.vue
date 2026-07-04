<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Lock, Users, KeySquare, Pencil, Trash2, ShieldCheck } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import EmptyState from '@/components/UI/EmptyState.vue';
import Tooltip from '@/components/UI/Tooltip.vue';
import { ROLE_ICONS } from '@/constants/icons';

interface RoleItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string;
    icon: string;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
}

defineProps<{ roles: RoleItem[] }>();

function deleteRole(role: RoleItem) {
    if (!confirm(`Delete role "${role.name}"? Users assigned to it will lose any permissions granted only by this role.`)) return;
    router.delete(route('admin.roles.destroy', role.id));
}
</script>

<template>
    <Head title="Roles & Permissions" />
    <AdminLayout title="Roles & Permissions">

        <PageHeader
            title="Roles & Permissions"
            description="Define what each role can do — assign permissions to roles and roles to users."
            :icon="ShieldCheck"
        >
            <template #actions>
                <Link
                    :href="route('admin.roles.create')"
                    class="flex items-center gap-1.5 bg-blue-500 text-white font-semibold rounded-lg px-3.5 py-2 text-sm hover:bg-blue-400 transition-colors"
                >
                    <Plus :size="14" :stroke-width="2" /> New Role
                </Link>
            </template>
        </PageHeader>

        <EmptyState
            v-if="roles.length === 0"
            :icon="ShieldCheck"
            title="No roles yet"
            description="Create your first role to start assigning permissions to users."
        >
            <template #action>
                <Link
                    :href="route('admin.roles.create')"
                    class="flex items-center gap-1.5 bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-400 transition-colors"
                >
                    <Plus :size="14" :stroke-width="2" /> New Role
                </Link>
            </template>
        </EmptyState>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div
                v-for="role in roles"
                :key="role.id"
                class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden flex flex-col hover:border-zinc-700/60 transition-colors"
            >
                <!-- Color accent bar -->
                <div class="h-[3px] w-full shrink-0" :style="{ background: role.color }" />

                <div class="p-5 flex flex-col gap-3 flex-1">
                    <!-- Header: icon + name + lock + actions -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3 min-w-0">
                            <span
                                class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                                :style="{ background: role.color + '1a', color: role.color }"
                            >
                                <component :is="ROLE_ICONS[role.icon] ?? ShieldCheck" :size="17" :stroke-width="1.75" />
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <h3 class="text-zinc-100 text-sm font-semibold truncate">{{ role.name }}</h3>
                                    <Tooltip v-if="role.is_system" text="System role — slug is locked and cannot be deleted">
                                        <Lock :size="11" :stroke-width="2" class="text-amber-400 shrink-0" />
                                    </Tooltip>
                                </div>
                                <p class="text-zinc-600 text-[11px] font-mono truncate">{{ role.slug }}</p>
                            </div>
                        </div>

                        <!-- Actions — always visible -->
                        <div class="flex items-center gap-0.5 shrink-0">
                            <Tooltip text="Edit role">
                                <Link
                                    :href="route('admin.roles.edit', role.id)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                                >
                                    <Pencil :size="13" :stroke-width="1.75" />
                                </Link>
                            </Tooltip>
                            <Tooltip v-if="!role.is_system" text="Delete role">
                                <button
                                    type="button"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                                    @click="deleteRole(role)"
                                >
                                    <Trash2 :size="13" :stroke-width="1.75" />
                                </button>
                            </Tooltip>
                            <!-- Placeholder so layout doesn't shift for system roles -->
                            <span v-else class="w-7 h-7 shrink-0" />
                        </div>
                    </div>

                    <!-- Description -->
                    <p class="text-zinc-500 text-xs leading-relaxed flex-1 min-h-[2rem]">
                        {{ role.description ?? 'No description provided.' }}
                    </p>

                    <!-- Footer stats -->
                    <div class="flex items-center gap-4 pt-3 border-t border-zinc-800/50">
                        <Tooltip text="Number of users with this role">
                            <div class="flex items-center gap-1.5 text-zinc-500 text-xs cursor-default hover:text-zinc-300 transition-colors">
                                <Users :size="12" :stroke-width="1.75" />
                                <span>{{ role.users_count }} user{{ role.users_count !== 1 ? 's' : '' }}</span>
                            </div>
                        </Tooltip>
                        <Tooltip text="Number of permissions assigned">
                            <div class="flex items-center gap-1.5 text-zinc-500 text-xs cursor-default hover:text-zinc-300 transition-colors">
                                <KeySquare :size="12" :stroke-width="1.75" />
                                <span>{{ role.permissions_count }} permission{{ role.permissions_count !== 1 ? 's' : '' }}</span>
                            </div>
                        </Tooltip>
                        <Link
                            :href="route('admin.roles.edit', role.id)"
                            class="ml-auto text-[11px] text-zinc-600 hover:text-blue-400 transition-colors"
                        >
                            Edit →
                        </Link>
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
