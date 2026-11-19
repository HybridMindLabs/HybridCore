<script setup lang="ts">
import { computed } from 'vue';
import { inputClass, type PlatformSettings, type SettingsForm } from '../settingsForm';

const props = defineProps<{ form: SettingsForm; settings: PlatformSettings }>();

const options = [
    { value: 'none',         label: 'None',         desc: 'No captcha' },
    { value: 'turnstile',    label: 'Cloudflare',   desc: 'Turnstile' },
    { value: 'hcaptcha',     label: 'hCaptcha',     desc: 'Privacy-first' },
    { value: 'recaptcha_v2', label: 'reCAPTCHA v2', desc: 'Google checkbox' },
    { value: 'recaptcha_v3', label: 'reCAPTCHA v3', desc: 'Google invisible' },
];

const providers = {
    turnstile:    { title: 'Cloudflare Turnstile Keys', placeholder: '0x4AAAA…' },
    hcaptcha:     { title: 'hCaptcha Keys',             placeholder: '10000000-ffff-…' },
    recaptcha_v2: { title: 'Google reCAPTCHA v2 Keys',  placeholder: '6LeIxAcT…' },
    recaptcha_v3: { title: 'Google reCAPTCHA v3 Keys',  placeholder: '6LcA…' },
} as const;

type Provider = keyof typeof providers;

/** Keys of the selected provider, or null for "none". */
const active = computed(() => {
    const id = props.form.captcha_provider as Provider;
    if (!(id in providers)) return null;

    return {
        ...providers[id],
        siteKey: `captcha_${id}_site_key` as const,
        secretKey: `captcha_${id}_secret_key` as const,
        secretSet: props.settings[`captcha_${id}_secret_key_set`],
    };
});
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col">
            <h3 class="text-zinc-100 text-sm font-semibold mb-4">Captcha Provider</h3>

            <div class="flex flex-col gap-2 py-3 border-t border-zinc-800/70">
                <p class="text-zinc-600 text-xs mb-2">Select the captcha service to protect forms. Choose "None" to disable captcha entirely.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2" role="radiogroup" aria-label="Captcha provider">
                    <button
                        v-for="opt in options"
                        :key="opt.value"
                        type="button"
                        role="radio"
                        :aria-checked="form.captcha_provider === opt.value"
                        class="flex flex-col items-start px-4 py-3 rounded-xl border transition-colors text-left"
                        :class="form.captcha_provider === opt.value
                            ? 'border-blue-500/60 bg-blue-500/10 text-blue-400'
                            : 'border-zinc-800 text-zinc-500 hover:border-zinc-700 hover:text-zinc-400'"
                        @click="form.captcha_provider = opt.value"
                    >
                        <span class="text-xs font-semibold">{{ opt.label }}</span>
                        <span class="text-[11px] opacity-70">{{ opt.desc }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="active" class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col">
            <h3 class="text-zinc-100 text-sm font-semibold mb-4">{{ active.title }}</h3>
            <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                <label for="set-captcha-site" class="text-zinc-400 text-xs font-medium">Site Key</label>
                <input id="set-captcha-site" v-model="form[active.siteKey]" type="text" :placeholder="active.placeholder" :class="[inputClass, 'font-mono']" />
            </div>
            <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                <label for="set-captcha-secret" class="text-zinc-400 text-xs font-medium">
                    Secret Key
                    <span v-if="active.secretSet" class="text-emerald-400 font-normal">— set</span>
                </label>
                <!-- new-password stops the browser autofilling the admin's own password here. -->
                <input id="set-captcha-secret" v-model="form[active.secretKey]" type="password" autocomplete="new-password"
                    :placeholder="active.secretSet ? 'Leave blank to keep' : 'Enter secret key'" :class="inputClass" />
            </div>
        </div>
    </div>
</template>
