<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ChevronLeft, Lock, ShieldCheck } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import IconPicker from '@/components/Admin/IconPicker.vue';
import PermissionTree from '@/components/Admin/PermissionTree.vue';
import { ROLE_ICONS } from '@/constants/icons';

interface PermissionGroup { group: string; permissions: { slug: string; name: string }[] }
interface RoleProp {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string;
    icon: string;
    is_system: boolean;
    users_count: number;
    permissions: string[];
}

const props = defineProps<{ role: RoleProp; permissionGroups: PermissionGroup[]; availableIcons: string[] }>();

const form = useForm({
    name: props.role.name,
    slug: props.role.slug,
    description: props.role.description ?? '',
    color: props.role.color,
    icon: props.role.icon,
    permissions: [...props.role.permissions] as string[],
});

const swatches = ['#ef4444', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#22d3ee', '#64748b', '#ec4899'];

function submit() {
    form.put(route('admin.roles.update', props.role.id));
}

function deleteRole() {
    if (!confirm(`Delete role "${props.role.name}"? Users assigned to it will lose any permissions granted only by this role.`)) return;
    router.delete(route('admin.roles.destroy', props.role.id));
}
</script>

<template>
    <Head :title="`Edit — ${role.name}`" />
    <AdminLayout :title="`Edit Role`">

        <div class="flex items-center gap-2 mb-6 text-sm">
            <Link :href="route('admin.roles.index')" class="flex items-center gap-1.5 text-zinc-500 hover:text-zinc-100 transition-colors">
                <ChevronLeft :size="13" :stroke-width="1.75" /> Roles
            </Link>
            <span class="text-zinc-700">/</span>
            <span class="text-zinc-300">{{ role.name }}</span>
        </div>

        <!-- System role notice -->
        <div v-if="role.is_system" class="mb-5 flex items-start gap-3 bg-[#f59e0b]/10 border border-[#f59e0b]/30 rounded-xl px-4 py-3">
            <Lock :size="15" :stroke-width="1.75" class="text-[#f59e0b] mt-0.5 shrink-0" />
            <div>
                <p class="text-[#f59e0b] text-sm font-medium">System role</p>
                <p class="text-zinc-400 text-xs mt-0.5">This is a built-in role. Its slug is locked, but you can change the name, description, appearance, and permissions.</p>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="flex flex-col xl:flex-row gap-5 items-start">

                <!-- Left: identity -->
                <div class="w-full xl:w-80 shrink-0 flex flex-col gap-4">

                    <!-- Preview card -->
                    <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-4">
                        <p class="text-zinc-600 text-[11px] uppercase tracking-wide font-medium mb-3">Preview</p>
                        <div class="flex items-center gap-3">
                            <span
                                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-all"
                                :style="{ backgroundColor: form.color + '1a', color: form.color }"
                            >
                                <component :is="ROLE_ICONS[form.icon] ?? ShieldCheck" :size="18" :stroke-width="1.75" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-zinc-100 text-sm font-semibold truncate">{{ form.name || 'Role name' }}</p>
                                <p class="text-zinc-600 text-[11px] font-mono truncate">{{ form.slug || 'role-slug' }}</p>
                            </div>
                        </div>
                        <p class="text-zinc-400 text-xs mt-3 leading-relaxed min-h-[2rem]">
                            {{ form.description || 'No description.' }}
                        </p>
                        <div class="mt-3 pt-3 border-t border-zinc-800/70 flex items-center gap-3">
                            <span
                                class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full"
                                :style="{ backgroundColor: form.color + '1a', color: form.color }"
                            >
                                <component :is="ROLE_ICONS[form.icon] ?? ShieldCheck" :size="10" :stroke-width="2" />
                                {{ form.name || 'Role' }}
                            </span>
                            <span class="text-zinc-600 text-[11px]">{{ role.users_count }} user{{ role.users_count !== 1 ? 's' : '' }}</span>
                        </div>
                    </div>

                    <!-- Identity fields -->
                    <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-4 flex flex-col gap-4">
                        <p class="text-zinc-600 text-[11px] uppercase tracking-wide font-medium">Identity</p>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm
                                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                                :class="form.errors.name ? 'border-red-500' : ''"
                            />
                            <p v-if="form.errors.name" class="text-red-400 text-xs">{{ form.errors.name }}</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">
                                Slug
                                <span v-if="role.is_system" class="text-zinc-600 font-normal">(locked)</span>
                            </label>
                            <input
                                v-model="form.slug"
                                type="text"
                                :disabled="role.is_system"
                                class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm font-mono
                                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 disabled:opacity-40 disabled:cursor-not-allowed"
                                :class="form.errors.slug ? 'border-red-500' : ''"
                            />
                            <p v-if="form.errors.slug" class="text-red-400 text-xs">{{ form.errors.slug }}</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Description <span class="text-zinc-600 font-normal">(optional)</span></label>
                            <textarea
                                v-model="form.description"
                                rows="2"
                                class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm resize-none
                                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                            />
                        </div>
                    </div>

                    <!-- Appearance -->
                    <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-4 flex flex-col gap-4">
                        <p class="text-zinc-600 text-[11px] uppercase tracking-wide font-medium">Appearance</p>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Color</label>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="form.color"
                                    type="color"
                                    class="w-9 h-9 rounded-lg border border-zinc-800/70 bg-zinc-900/60 cursor-pointer shrink-0"
                                />
                                <input
                                    v-model="form.color"
                                    type="text"
                                    class="flex-1 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm font-mono
                                           focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                                />
                            </div>
                            <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                <button
                                    v-for="swatch in swatches"
                                    :key="swatch"
                                    type="button"
                                    class="w-5 h-5 rounded-full border-2 transition-transform hover:scale-110"
                                    :class="form.color === swatch ? 'border-white/70' : 'border-transparent'"
                                    :style="{ backgroundColor: swatch }"
                                    @click="form.color = swatch"
                                />
                            </div>
                            <p v-if="form.errors.color" class="text-red-400 text-xs">{{ form.errors.color }}</p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Icon</label>
                            <IconPicker v-model="form.icon" :color="form.color" />
                            <p v-if="form.errors.icon" class="text-red-400 text-xs">{{ form.errors.icon }}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex-1 bg-blue-500 text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-blue-400 transition-colors disabled:opacity-60 text-center"
                        >
                            Save Changes
                        </button>
                        <Link :href="route('admin.roles.index')" class="text-zinc-400 hover:text-zinc-100 text-sm transition-colors">
                            Cancel
                        </Link>
                    </div>

                    <!-- Danger zone -->
                    <div v-if="!role.is_system" class="bg-[#111113] border border-red-500/20 rounded-xl p-4">
                        <p class="text-red-400 text-xs font-semibold mb-1">Danger Zone</p>
                        <p class="text-zinc-600 text-xs mb-3">Permanently deletes this role. Users assigned to it will lose any permissions granted only by it.</p>
                        <button
                            type="button"
                            class="bg-red-500/10 text-red-400 border border-red-500/30 rounded-lg px-3 py-1.5 text-xs hover:bg-red-500/15 transition-colors"
                            @click="deleteRole"
                        >
                            Delete role
                        </button>
                    </div>
                </div>

                <!-- Right: permissions -->
                <div class="flex-1 min-w-0">
                    <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-zinc-100 text-sm font-semibold">Permissions</p>
                            <p class="text-zinc-600 text-xs">Grant which actions this role can perform.</p>
                        </div>
                        <PermissionTree v-model="form.permissions" :groups="permissionGroups" />
                        <p v-if="form.errors.permissions" class="text-red-400 text-xs mt-2">{{ form.errors.permissions }}</p>
                    </div>
                </div>

            </div>
        </form>

    </AdminLayout>
</template>
