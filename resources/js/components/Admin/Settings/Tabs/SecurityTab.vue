<script setup lang="ts">
import SettingsToggleRow from '../SettingsToggleRow.vue';
import { numberClass, type SettingsForm } from '../settingsForm';

defineProps<{ form: SettingsForm }>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
            <h3 class="text-zinc-100 text-sm font-semibold">Password Requirements</h3>
            <p class="text-zinc-600 text-xs -mt-2">Applied when users register or change their password.</p>

            <div class="flex flex-col gap-1.5">
                <label for="set-password-min" class="text-zinc-400 text-xs font-medium">Minimum length</label>
                <div class="flex items-center gap-3">
                    <input id="set-password-min" v-model.number="form.password_min_length" type="number" min="8" max="128" :class="numberClass" />
                    <p class="text-zinc-600 text-xs">characters (min 8, max 128)</p>
                </div>
                <p v-if="form.errors.password_min_length" class="text-red-400 text-xs">{{ form.errors.password_min_length }}</p>
            </div>

            <div class="flex flex-col">
                <SettingsToggleRow v-model="form.password_require_mixed" title="Require mixed case"
                    hint="Password must contain both uppercase and lowercase letters." />
                <SettingsToggleRow v-model="form.password_require_numbers" title="Require numbers"
                    hint="Password must contain at least one digit." />
            </div>
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
            <h3 class="text-zinc-100 text-sm font-semibold">Two-Factor Authentication</h3>
            <p class="text-zinc-600 text-xs -mt-2">Require every admin-panel user to set up 2FA, with a per-admin grace period instead of a hard cutover.</p>

            <SettingsToggleRow v-model="form.require_2fa_for_admins" :divided="false" title="Require 2FA for admin access"
                hint="Each admin gets their own grace window starting the first time this applies to them." />

            <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70" :class="!form.require_2fa_for_admins ? 'opacity-50 pointer-events-none' : ''">
                <label for="set-2fa-grace" class="text-zinc-400 text-xs font-medium">Grace period</label>
                <div class="flex items-center gap-3">
                    <input id="set-2fa-grace" v-model.number="form.require_2fa_grace_days" type="number" min="0" max="90"
                        :disabled="!form.require_2fa_for_admins" :class="numberClass" />
                    <p class="text-zinc-600 text-xs">days before an admin without 2FA is locked out (0 = immediately)</p>
                </div>
            </div>
        </div>
    </div>
</template>
