<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { computed } from 'vue';

interface SharedProps {
    auth: { user: { id: number; name: string; email: string } | null };
}

interface AlertPref { key: string; label: string; description: string; database: boolean; mail: boolean }

const props = defineProps<{ alerts: AlertPref[] }>();

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth?.user);

const alertsForm = useForm({ alerts: props.alerts.map((a) => ({ key: a.key, database: a.database, mail: a.mail })) });

function updateAlerts() {
    alertsForm.put(route('admin.profile.alerts'), { preserveScroll: true });
}

const profileForm = useForm({
    name:  user.value?.name  ?? '',
    email: user.value?.email ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updateProfile() {
    profileForm.put(route('admin.profile.update'));
}

function updatePassword() {
    passwordForm.put(route('admin.profile.password'), {
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head title="Profile" />
    <AdminLayout title="Profile">

        <div class="mb-6">
            <h2 class="text-zinc-100 text-xl font-semibold">My Profile</h2>
            <p class="text-zinc-400 text-sm mt-1">Update your name, email and password.</p>
        </div>

        <div class="max-w-xl flex flex-col gap-4">

            <!-- Profile info -->
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
                <h3 class="text-zinc-100 text-sm font-semibold mb-4">Account Details</h3>

                <form @submit.prevent="updateProfile" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Full name</label>
                        <input
                            v-model="profileForm.name"
                            type="text"
                            class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm
                                   focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                            :class="profileForm.errors.name ? 'border-red-500' : ''"
                        />
                        <p v-if="profileForm.errors.name" class="text-red-400 text-xs">{{ profileForm.errors.name }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Email address</label>
                        <input
                            v-model="profileForm.email"
                            type="email"
                            class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm
                                   focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                            :class="profileForm.errors.email ? 'border-red-500' : ''"
                        />
                        <p v-if="profileForm.errors.email" class="text-red-400 text-xs">{{ profileForm.errors.email }}</p>
                    </div>

                    <div>
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="bg-blue-500 text-[#0a0f1a] font-semibold rounded-lg px-4 py-2 text-sm hover:bg-blue-600 transition-colors disabled:opacity-60"
                        >
                            Update profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password -->
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
                <h3 class="text-zinc-100 text-sm font-semibold mb-4">Change Password</h3>

                <form @submit.prevent="updatePassword" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Current password</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm
                                   focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                            :class="passwordForm.errors.current_password ? 'border-red-500' : ''"
                        />
                        <p v-if="passwordForm.errors.current_password" class="text-red-400 text-xs">{{ passwordForm.errors.current_password }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">New password</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm
                                   focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                            :class="passwordForm.errors.password ? 'border-red-500' : ''"
                        />
                        <p v-if="passwordForm.errors.password" class="text-red-400 text-xs">{{ passwordForm.errors.password }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Confirm new password</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            class="bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm
                                   focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                        />
                    </div>

                    <div>
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="bg-zinc-900/60 text-zinc-100 border border-zinc-800/70 font-semibold rounded-lg px-4 py-2 text-sm hover:bg-[#22334a] transition-colors disabled:opacity-60"
                        >
                            Update password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Notifications -->
            <div v-if="alerts.length" class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
                <h3 class="text-zinc-100 text-sm font-semibold">Notifications</h3>
                <p class="text-zinc-500 text-xs mt-1 mb-4">Choose how you hear about things that need attention. Only alerts your role can act on are listed.</p>

                <form @submit.prevent="updateAlerts">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wider text-zinc-500">
                                <th class="pb-2 font-semibold">Alert</th>
                                <th class="pb-2 font-semibold w-24 text-center">In panel</th>
                                <th class="pb-2 font-semibold w-24 text-center">Email</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/50">
                            <tr v-for="(a, i) in alerts" :key="a.key">
                                <td class="py-3 pr-4">
                                    <p class="text-zinc-200">{{ a.label }}</p>
                                    <p class="text-zinc-500 text-xs">{{ a.description }}</p>
                                </td>
                                <td class="py-3 text-center">
                                    <input v-model="alertsForm.alerts[i].database" type="checkbox" :aria-label="`${a.label} in panel`" class="w-4 h-4 rounded accent-blue-500" />
                                </td>
                                <td class="py-3 text-center">
                                    <input v-model="alertsForm.alerts[i].mail" type="checkbox" :aria-label="`${a.label} by email`" class="w-4 h-4 rounded accent-blue-500" />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <button
                        type="submit"
                        :disabled="alertsForm.processing || !alertsForm.isDirty"
                        class="mt-4 bg-blue-500 text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-blue-400 transition-colors disabled:opacity-50"
                    >
                        Save notifications
                    </button>
                </form>
            </div>

        </div>

    </AdminLayout>
</template>
