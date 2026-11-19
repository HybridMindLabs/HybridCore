<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { ref } from 'vue';
import { inputClass, type SettingsForm } from '../settingsForm';

defineProps<{ form: SettingsForm }>();

const testEmailAddress = ref('');
const testEmailSending = ref(false);

function sendTestEmail() {
    if (!testEmailAddress.value || testEmailSending.value) return;
    testEmailSending.value = true;
    router.post(route('admin.settings.test-email'), { email: testEmailAddress.value }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { testEmailSending.value = false; },
    });
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col">
            <h3 class="text-zinc-100 text-sm font-semibold mb-4">Contact Form</h3>

            <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                <label for="set-contact-email" class="text-zinc-400 text-xs font-medium">Recipient email</label>
                <input
                    id="set-contact-email"
                    v-model="form.contact_recipient_email"
                    type="email"
                    placeholder="admin@example.com"
                    :class="[inputClass, form.errors.contact_recipient_email ? 'border-red-500' : '']"
                />
                <p class="text-zinc-600 text-xs">Where contact form submissions are sent via email.</p>
                <p v-if="form.errors.contact_recipient_email" class="text-xs text-red-400">{{ form.errors.contact_recipient_email }}</p>
            </div>
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
            <h3 class="text-zinc-100 text-sm font-semibold mb-1">Test Email Delivery</h3>
            <p class="text-zinc-600 text-xs mb-4">Send a test email to verify your SMTP configuration is working correctly.</p>
            <div class="flex gap-2">
                <!-- Enter here must send the test, not submit the whole settings form. -->
                <input
                    v-model="testEmailAddress"
                    type="email"
                    placeholder="you@example.com"
                    aria-label="Test email address"
                    :class="inputClass"
                    @keydown.enter.prevent="sendTestEmail"
                />
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-500 transition-colors shrink-0 disabled:opacity-50"
                    :disabled="testEmailSending || !testEmailAddress"
                    @click="sendTestEmail"
                >
                    <Send :size="13" :stroke-width="1.75" />
                    {{ testEmailSending ? 'Sending…' : 'Send Test' }}
                </button>
            </div>
        </div>
    </div>
</template>
