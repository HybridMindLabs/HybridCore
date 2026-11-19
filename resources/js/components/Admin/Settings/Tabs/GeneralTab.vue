<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Link2, Locate, Paintbrush } from '@lucide/vue';
import SettingsToggleRow from '../SettingsToggleRow.vue';
import { inputClass, type PlatformSettings, type SettingsForm } from '../settingsForm';

const props = defineProps<{ form: SettingsForm; settings: PlatformSettings; timezones: string[] }>();

function useCurrentUrl() {
    props.form.app_url = window.location.origin;
}

const detectedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
function detectTimezone() {
    if (props.timezones.includes(detectedTimezone)) props.form.timezone = detectedTimezone;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
            <h3 class="text-zinc-100 text-sm font-semibold">Application</h3>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="set-app-name" class="text-zinc-400 text-xs font-medium">Application name</label>
                    <input id="set-app-name" v-model="form.app_name" type="text" :class="[inputClass, form.errors.app_name ? 'border-red-500' : '']" placeholder="My Platform" />
                    <p class="text-zinc-600 text-xs">Shown in the browser title bar, emails, and the admin panel header.</p>
                    <p v-if="form.errors.app_name" class="text-red-400 text-xs">{{ form.errors.app_name }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="set-app-url" class="text-zinc-400 text-xs font-medium">Application URL</label>
                    <div class="flex gap-2">
                        <input id="set-app-url" v-model="form.app_url" type="url" :class="[inputClass, form.errors.app_url ? 'border-red-500' : '']" placeholder="https://example.com" />
                        <button
                            type="button"
                            title="Fill in the URL you're currently browsing this admin panel from"
                            class="flex items-center gap-1.5 px-3 rounded-lg text-xs font-medium border border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 transition-colors shrink-0"
                            @click="useCurrentUrl"
                        >
                            <Link2 :size="12" :stroke-width="1.75" /> Use current
                        </button>
                    </div>
                    <p class="text-zinc-600 text-xs">The public-facing URL. Used for generating links in emails and OAuth callbacks.</p>
                    <p v-if="form.errors.app_url" class="text-red-400 text-xs">{{ form.errors.app_url }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="set-timezone" class="text-zinc-400 text-xs font-medium">
                    Timezone <span class="text-zinc-600 font-normal">— default locale lives on the Localization tab</span>
                </label>
                <div class="flex gap-2">
                    <select id="set-timezone" v-model="form.timezone" :class="inputClass">
                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                    <button
                        type="button"
                        :title="`Detected: ${detectedTimezone}`"
                        class="flex items-center gap-1.5 px-3 rounded-lg text-xs font-medium border border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 transition-colors shrink-0"
                        @click="detectTimezone"
                    >
                        <Locate :size="12" :stroke-width="1.75" /> Detect
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
            <h3 class="text-zinc-100 text-sm font-semibold">Theme & Availability</h3>

            <div class="flex flex-col gap-1.5">
                <label class="text-zinc-400 text-xs font-medium">Active theme</label>
                <Link
                    :href="route('admin.themes.index')"
                    class="flex items-center justify-between px-3.5 py-2.5 rounded-lg border border-zinc-800/70 bg-zinc-900/60 hover:border-blue-500/40 hover:bg-blue-500/5 transition-colors group"
                >
                    <span class="flex items-center gap-2">
                        <Paintbrush :size="13" :stroke-width="1.75" class="text-zinc-500" />
                        <span class="text-sm text-zinc-200">{{ settings.active_theme_name ?? settings.active_theme }}</span>
                    </span>
                    <span class="flex items-center gap-1 text-xs text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity">
                        Change on Themes page <ArrowRight :size="11" :stroke-width="1.75" />
                    </span>
                </Link>
                <p class="text-zinc-600 text-xs">Managed from the <Link :href="route('admin.themes.index')" class="text-blue-400 hover:underline">Themes page</Link> so activation always goes through its safety checks — not editable here.</p>
            </div>

            <SettingsToggleRow v-model="form.maintenance_mode" title="Maintenance mode"
                hint="Hides public-facing pages from non-admin visitors." on-class="bg-[#f59e0b]" />
        </div>
    </div>
</template>
