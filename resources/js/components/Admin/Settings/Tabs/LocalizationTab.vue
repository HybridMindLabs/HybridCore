<script setup lang="ts">
import SettingsToggleRow from '../SettingsToggleRow.vue';
import { inputClass, type SettingsForm } from '../settingsForm';

defineProps<{ form: SettingsForm; localeCatalog: Record<string, { name: string; native_name: string }> }>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
            <h3 class="text-zinc-100 text-sm font-semibold">Language Settings</h3>

            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="set-default-locale" class="text-zinc-400 text-xs font-medium">Default locale</label>
                    <select id="set-default-locale" v-model="form.loc_default_locale" :class="inputClass">
                        <option v-for="(meta, code) in localeCatalog" :key="code" :value="code">
                            {{ meta.native_name }} ({{ code }})
                        </option>
                    </select>
                    <p class="text-zinc-600 text-xs">Language shown to visitors who haven't picked one — the single site-wide default.</p>
                    <p v-if="form.errors.loc_default_locale" class="text-red-400 text-xs">{{ form.errors.loc_default_locale }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="set-fallback-locale" class="text-zinc-400 text-xs font-medium">Fallback locale</label>
                    <select id="set-fallback-locale" v-model="form.loc_fallback_locale" :class="inputClass">
                        <option v-for="(meta, code) in localeCatalog" :key="code" :value="code">
                            {{ meta.native_name }} ({{ code }})
                        </option>
                    </select>
                    <p class="text-zinc-600 text-xs">Used when a string is missing in the default locale.</p>
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="set-supported-locales" class="text-zinc-400 text-xs font-medium">Supported locales</label>
                <input id="set-supported-locales" v-model="form.loc_supported_locales" type="text" placeholder="en,bg" :class="[inputClass, 'font-mono']" />
                <p class="text-zinc-600 text-xs">
                    Comma-separated codes (e.g. <code class="text-blue-400">en,bg</code>).
                    Available: {{ Object.keys(localeCatalog).join(', ') }}
                </p>
                <p v-if="form.errors.loc_supported_locales" class="text-red-400 text-xs">{{ form.errors.loc_supported_locales }}</p>
            </div>
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-1">
            <h3 class="text-zinc-100 text-sm font-semibold mb-3">Language Switcher</h3>
            <SettingsToggleRow v-model="form.loc_public_switcher" title="Public language switcher"
                hint="Show a language picker on the public-facing website." />
            <SettingsToggleRow v-model="form.loc_admin_switcher" title="Admin language switcher"
                hint="Show a language picker in the admin panel topbar." />
        </div>
    </div>
</template>
