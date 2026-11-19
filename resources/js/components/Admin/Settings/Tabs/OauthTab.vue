<script setup lang="ts">
import SettingsSwitch from '../SettingsSwitch.vue';
import SettingsToggleRow from '../SettingsToggleRow.vue';
import { inputClass, type PlatformSettings, type SettingsForm } from '../settingsForm';

defineProps<{ form: SettingsForm; settings: PlatformSettings }>();

const secretPlaceholder = (isSet: boolean, noun = 'secret') => isSet ? 'Leave blank to keep' : `Enter ${noun}`;
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl px-5 py-2">
            <SettingsToggleRow v-model="form.oauth_enabled" :divided="false" title="Enable OAuth sign-in"
                hint="Master switch — turns all OAuth providers on or off at once." />
        </div>

        <!-- Discord -->
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4" :class="!form.oauth_enabled ? 'opacity-50 pointer-events-none' : ''">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-[#5865f2]/15 flex items-center justify-center">
                        <svg class="w-4 h-4 text-[#5865f2]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057.1 18.08.11 18.103.129 18.117a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/></svg>
                    </span>
                    <p class="text-zinc-100 text-sm font-semibold">Discord</p>
                </div>
                <SettingsSwitch v-model="form.oauth_discord_enabled" label="Discord sign-in" on-class="bg-[#5865f2]" />
            </div>

            <div class="grid grid-cols-2 gap-3 pt-1" :class="!form.oauth_discord_enabled ? 'opacity-40' : ''">
                <div class="flex flex-col gap-1.5">
                    <label for="set-discord-id" class="text-zinc-400 text-xs font-medium">Client ID</label>
                    <input id="set-discord-id" v-model="form.oauth_discord_client_id" type="text" :class="inputClass" :disabled="!form.oauth_discord_enabled" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="set-discord-secret" class="text-zinc-400 text-xs font-medium">
                        Client Secret
                        <span v-if="settings.oauth_discord_client_secret_set" class="text-emerald-400 font-normal">— set</span>
                    </label>
                    <input id="set-discord-secret" v-model="form.oauth_discord_client_secret" type="password" autocomplete="new-password" :class="inputClass" :disabled="!form.oauth_discord_enabled"
                           :placeholder="secretPlaceholder(settings.oauth_discord_client_secret_set)" />
                </div>
            </div>
        </div>

        <!-- Steam -->
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4" :class="!form.oauth_enabled ? 'opacity-50 pointer-events-none' : ''">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-[#1b2838]/80 border border-[#2a475e] flex items-center justify-center">
                        <svg class="w-4 h-4 text-[#c7d5e0]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.031 4.524 4.527s-2.03 4.525-4.524 4.525h-.105l-4.076 2.911c0 .052.004.105.004.159 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.727L.436 15.27C1.862 20.307 6.486 24 11.979 24c6.627 0 11.999-5.373 11.999-12S18.606 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.999 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.63.264-1.319.005-1.949s-.75-1.121-1.377-1.383c-.624-.26-1.29-.249-1.878-.03l1.523.63c.956.4 1.409 1.5 1.009 2.455-.397.957-1.497 1.41-2.455 1.012H7.54zm11.415-9.303c0-1.662-1.353-3.015-3.015-3.015-1.665 0-3.015 1.353-3.015 3.015 0 1.665 1.35 3.015 3.015 3.015 1.662 0 3.015-1.35 3.015-3.015zm-5.273.005c0-1.252 1.013-2.266 2.265-2.266 1.249 0 2.266 1.014 2.266 2.266 0 1.251-1.017 2.265-2.266 2.265-1.252 0-2.265-1.014-2.265-2.265z"/></svg>
                    </span>
                    <p class="text-zinc-100 text-sm font-semibold">Steam</p>
                </div>
                <SettingsSwitch v-model="form.oauth_steam_enabled" label="Steam sign-in" on-class="bg-[#4c6b22]" />
            </div>

            <div class="pt-1" :class="!form.oauth_steam_enabled ? 'opacity-40' : ''">
                <div class="flex flex-col gap-1.5 max-w-sm">
                    <label for="set-steam-key" class="text-zinc-400 text-xs font-medium">
                        Steam Web API Key
                        <span v-if="settings.oauth_steam_client_secret_set" class="text-emerald-400 font-normal">— set</span>
                    </label>
                    <input id="set-steam-key" v-model="form.oauth_steam_client_secret" type="password" autocomplete="new-password" :class="inputClass" :disabled="!form.oauth_steam_enabled"
                           :placeholder="secretPlaceholder(settings.oauth_steam_client_secret_set, 'API key')" />
                </div>
                <p class="text-zinc-600 text-xs mt-2">Steam doesn't provide an email — new accounts will be prompted to add one after signing in.</p>
            </div>
        </div>

        <!-- Google -->
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4" :class="!form.oauth_enabled ? 'opacity-50 pointer-events-none' : ''">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-white/90 border border-zinc-300/40 flex items-center justify-center">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1A6.6 6.6 0 0 1 5.5 12c0-.73.13-1.44.34-2.1V7.06H2.18A11 11 0 0 0 1 12c0 1.77.43 3.45 1.18 4.94l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.3 9.14 5.38 12 5.38z"/></svg>
                    </span>
                    <p class="text-zinc-100 text-sm font-semibold">Google</p>
                </div>
                <SettingsSwitch v-model="form.oauth_google_enabled" label="Google sign-in" on-class="bg-[#4285F4]" />
            </div>

            <div class="grid grid-cols-2 gap-3 pt-1" :class="!form.oauth_google_enabled ? 'opacity-40' : ''">
                <div class="flex flex-col gap-1.5">
                    <label for="set-google-id" class="text-zinc-400 text-xs font-medium">Client ID</label>
                    <input id="set-google-id" v-model="form.oauth_google_client_id" type="text" :class="inputClass" :disabled="!form.oauth_google_enabled" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="set-google-secret" class="text-zinc-400 text-xs font-medium">
                        Client Secret
                        <span v-if="settings.oauth_google_client_secret_set" class="text-emerald-400 font-normal">— set</span>
                    </label>
                    <input id="set-google-secret" v-model="form.oauth_google_client_secret" type="password" autocomplete="new-password" :class="inputClass" :disabled="!form.oauth_google_enabled"
                           :placeholder="secretPlaceholder(settings.oauth_google_client_secret_set)" />
                </div>
            </div>
        </div>
    </div>
</template>
