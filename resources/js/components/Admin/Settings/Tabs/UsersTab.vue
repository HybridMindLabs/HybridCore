<script setup lang="ts">
import { computed } from 'vue';
import SettingsToggleRow from '../SettingsToggleRow.vue';
import { inputClass, numberClass, type SettingsForm } from '../settingsForm';

const props = defineProps<{ form: SettingsForm; roles: { id: number; name: string; slug: string; color: string }[] }>();

const roleMissing = computed(() => !props.roles.some(r => r.slug === props.form.default_user_role));
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col">
            <h3 class="text-zinc-100 text-sm font-semibold">Registration</h3>
            <p class="text-zinc-600 text-xs mt-1 mb-3">Controls how visitors turn into accounts on the public site.</p>
            <SettingsToggleRow v-model="form.registration_enabled" title="Allow public registration"
                hint="New visitors can create an account on the public site." />
            <SettingsToggleRow v-model="form.email_verification_required" title="Require email verification"
                hint="New accounts must verify their email before accessing the site." />
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
            <h3 class="text-zinc-100 text-sm font-semibold">Defaults</h3>

            <div class="flex flex-col gap-1.5">
                <label for="set-default-role" class="text-zinc-400 text-xs font-medium">Default user role</label>
                <select id="set-default-role" v-model="form.default_user_role" :class="[inputClass, 'max-w-[260px]']">
                    <option v-for="role in roles" :key="role.slug" :value="role.slug">{{ role.name }}</option>
                </select>
                <p class="text-zinc-600 text-xs">Assigned automatically to every new account on registration. Doesn't affect existing users.</p>
                <p v-if="roleMissing" class="text-amber-400 text-xs">
                    Current value "{{ form.default_user_role }}" doesn't match any existing role — pick one to fix it.
                </p>
            </div>
            <div class="flex flex-col gap-1.5 pt-3 border-t border-zinc-800/70">
                <label for="set-username-cooldown" class="text-zinc-400 text-xs font-medium">Username change cooldown (days)</label>
                <input id="set-username-cooldown" v-model.number="form.username_change_cooldown_days" type="number" min="0" max="365" :class="numberClass" />
                <p class="text-zinc-600 text-xs">How many days users must wait before changing their username again. 0 = no limit.</p>
            </div>
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col">
            <h3 class="text-zinc-100 text-sm font-semibold mb-4">Avatars & Banners</h3>
            <SettingsToggleRow v-model="form.avatar_enabled" title="Allow avatar uploads"
                hint="Users can upload a custom profile picture." />
            <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                <label for="set-avatar-kb" class="text-zinc-400 text-xs font-medium">Max avatar size (KB)</label>
                <input id="set-avatar-kb" v-model.number="form.avatar_max_kb" type="number" min="128" max="10240" :disabled="!form.avatar_enabled" :class="numberClass" />
            </div>
            <SettingsToggleRow v-model="form.banner_enabled" title="Allow banner uploads"
                hint="Users can upload a profile banner image (1200×300)." />
            <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                <label for="set-banner-kb" class="text-zinc-400 text-xs font-medium">Max banner size (KB)</label>
                <input id="set-banner-kb" v-model.number="form.banner_max_kb" type="number" min="512" max="20480" :disabled="!form.banner_enabled" :class="numberClass" />
            </div>
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col">
            <h3 class="text-zinc-100 text-sm font-semibold mb-4">Private Messages</h3>
            <SettingsToggleRow v-model="form.dm_enabled" title="Enable DMs"
                hint="Allow members to send direct messages to each other." />
            <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                <label for="set-dm-daily" class="text-zinc-400 text-xs font-medium">Daily message limit per user</label>
                <input id="set-dm-daily" v-model.number="form.dm_daily_limit" type="number" min="1" max="10000" :disabled="!form.dm_enabled" :class="numberClass" />
            </div>
            <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                <label for="set-dm-length" class="text-zinc-400 text-xs font-medium">Max message length (chars)</label>
                <input id="set-dm-length" v-model.number="form.dm_max_length" type="number" min="100" max="10000" :disabled="!form.dm_enabled" :class="numberClass" />
            </div>
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col">
            <h3 class="text-zinc-100 text-sm font-semibold mb-4">Notifications</h3>
            <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                <label for="set-notif-days" class="text-zinc-400 text-xs font-medium">Retain notifications for (days)</label>
                <div class="flex items-center gap-2">
                    <input id="set-notif-days" v-model.number="form.notif_retention_days" type="number" min="7" max="365" :class="numberClass" />
                    <p class="text-zinc-600 text-xs">days</p>
                </div>
            </div>
        </div>
    </div>
</template>
