<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    provider: string;
    siteKey: string | null;
}>();

const emit = defineEmits<{
    (e: 'token', value: string): void;
    (e: 'error'): void;
}>();

const widgetRef = ref<HTMLElement | null>(null);
let widgetId: string | number | null = null;

const scriptId = 'captcha-script';

function injectScript(src: string, onLoad: () => void) {
    if (document.getElementById(scriptId)) {
        onLoad();
        return;
    }
    const s = document.createElement('script');
    s.id = scriptId;
    s.src = src;
    s.async = true;
    s.defer = true;
    s.onload = onLoad;
    document.head.appendChild(s);
}

onMounted(() => {
    if (!props.siteKey || props.provider === 'none') return;

    if (props.provider === 'turnstile') {
        injectScript('https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', () => {
            widgetId = (window as any).turnstile.render(widgetRef.value, {
                sitekey: props.siteKey,
                callback: (token: string) => emit('token', token),
                'error-callback': () => emit('error'),
            });
        });
    }

    if (props.provider === 'hcaptcha') {
        injectScript('https://js.hcaptcha.com/1/api.js?render=explicit', () => {
            widgetId = (window as any).hcaptcha.render(widgetRef.value, {
                sitekey: props.siteKey,
                callback: (token: string) => emit('token', token),
                'error-callback': () => emit('error'),
            });
        });
    }

    if (props.provider === 'recaptcha_v2') {
        injectScript('https://www.google.com/recaptcha/api.js?render=explicit', () => {
            widgetId = (window as any).grecaptcha.render(widgetRef.value, {
                sitekey: props.siteKey,
                callback: (token: string) => emit('token', token),
                'error-callback': () => emit('error'),
            });
        });
    }

    if (props.provider === 'recaptcha_v3') {
        injectScript(`https://www.google.com/recaptcha/api.js?render=${props.siteKey}`, () => {
            (window as any).grecaptcha.ready(() => {
                (window as any).grecaptcha.execute(props.siteKey, { action: 'contact' })
                    .then((token: string) => emit('token', token));
            });
        });
    }
});

onUnmounted(() => {
    const el = document.getElementById(scriptId);
    if (el) el.remove();
});
</script>

<template>
    <div v-if="provider !== 'none' && siteKey">
        <!-- v3 is invisible — no widget div needed -->
        <div v-if="provider !== 'recaptcha_v3'" ref="widgetRef" />
        <p v-if="provider === 'recaptcha_v3'" class="text-xs text-zinc-500 mt-2">
            Protected by reCAPTCHA v3
        </p>
    </div>
</template>
