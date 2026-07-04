<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import EmailTabs from '@/components/Admin/Email/EmailTabs.vue';
import { Mail, FlaskConical, Save } from '@lucide/vue';
import { ref } from 'vue';

interface MailSettings {
    mail_mailer: string;
    mail_host: string;
    mail_port: string;
    mail_username: string;
    mail_password: string;
    mail_encryption: string;
    mail_from_address: string;
    mail_from_name: string;
}

const props = defineProps<{ settings: MailSettings }>();

const form = useForm({ ...props.settings });

const testEmail = ref('');
const testResult = ref<{ success: boolean; message: string } | null>(null);
const testLoading = ref(false);
const connResult = ref<{ success: boolean; message: string } | null>(null);
const connLoading = ref(false);

function save() {
    form.post(route('admin.email.settings.save'), { preserveScroll: true });
}

async function testConnection() {
    connLoading.value = true;
    connResult.value = null;
    try {
        const res = await fetch(route('admin.email.test-connection'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content },
        });
        connResult.value = await res.json();
    } finally {
        connLoading.value = false;
    }
}

async function sendTest() {
    if (!testEmail.value) return;
    testLoading.value = true;
    testResult.value = null;
    try {
        const res = await fetch(route('admin.email.send-test'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content },
            body: JSON.stringify({ email: testEmail.value }),
        });
        testResult.value = await res.json();
    } finally {
        testLoading.value = false;
    }
}

const breadcrumbs = [
    { label: 'Admin', href: route('admin.dashboard') },
    { label: 'Email Settings' },
];
</script>

<template>
    <Head title="Email Settings" />
    <AdminLayout title="Email Settings">
        <Breadcrumb :items="breadcrumbs" />
        <EmailTabs active="settings" />

        <div class="flex items-center gap-2 mb-5">
            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                <Mail :size="14" :stroke-width="1.8" class="text-cyan-400" />
            </div>
            <div>
                <h1 class="text-zinc-100 text-[15px] font-black">Email Settings</h1>
                <p class="text-zinc-600 text-[11px]">Configure SMTP and sender defaults</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- SMTP Config -->
            <div class="lg:col-span-2 bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <h2 class="text-zinc-300 text-sm font-bold mb-4">SMTP Configuration</h2>
                <form @submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Mailer</label>
                        <select v-model="form.mail_mailer"
                            class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                            <option value="smtp">SMTP</option>
                            <option value="log">Log (debug)</option>
                            <option value="sendmail">Sendmail</option>
                        </select>
                    </div>

                    <template v-if="form.mail_mailer === 'smtp'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Host</label>
                                <input v-model="form.mail_host" type="text" placeholder="smtp.example.com"
                                    class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500" />
                            </div>
                            <div>
                                <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Port</label>
                                <input v-model="form.mail_port" type="number" placeholder="587"
                                    class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Username</label>
                                <input v-model="form.mail_username" type="text" autocomplete="off"
                                    class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500" />
                            </div>
                            <div>
                                <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Password <span class="text-zinc-700 normal-case">(leave blank to keep)</span></label>
                                <input v-model="form.mail_password" type="password" autocomplete="new-password"
                                    class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">Encryption</label>
                            <select v-model="form.mail_encryption"
                                class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </select>
                        </div>
                    </template>

                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-zinc-800">
                        <div>
                            <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">From Address</label>
                            <input v-model="form.mail_from_address" type="email" placeholder="noreply@example.com"
                                class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500" />
                        </div>
                        <div>
                            <label class="block text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1">From Name</label>
                            <input v-model="form.mail_from_name" type="text"
                                class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                            class="flex items-center gap-1.5 bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-sm font-semibold rounded-lg px-4 py-2 hover:bg-cyan-500/30 transition-colors disabled:opacity-50">
                            <Save :size="13" />
                            Save Settings
                        </button>
                        <span v-if="form.recentlySuccessful" class="text-emerald-400 text-xs">Saved!</span>
                    </div>
                </form>
            </div>

            <!-- Test panel -->
            <div class="space-y-4">
                <!-- Connection test -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-4">
                    <h2 class="text-zinc-300 text-sm font-bold mb-3">Test Connection</h2>
                    <button type="button" @click="testConnection" :disabled="connLoading"
                        class="w-full flex items-center justify-center gap-1.5 bg-zinc-800 border border-zinc-700 text-zinc-300 text-sm font-semibold rounded-lg px-3 py-2 hover:bg-zinc-700 transition-colors disabled:opacity-50">
                        <FlaskConical :size="13" />
                        {{ connLoading ? 'Testing…' : 'Test Connection' }}
                    </button>
                    <div v-if="connResult" :class="connResult.success ? 'text-emerald-400' : 'text-red-400'" class="mt-2 text-xs">
                        {{ connResult.message }}
                    </div>
                </div>

                <!-- Send test email -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-4">
                    <h2 class="text-zinc-300 text-sm font-bold mb-3">Send Test Email</h2>
                    <input v-model="testEmail" type="email" placeholder="you@example.com"
                        class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-cyan-500 mb-2" />
                    <button type="button" @click="sendTest" :disabled="testLoading || !testEmail"
                        class="w-full flex items-center justify-center gap-1.5 bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 text-sm font-semibold rounded-lg px-3 py-2 hover:bg-cyan-500/30 transition-colors disabled:opacity-50">
                        <Mail :size="13" />
                        {{ testLoading ? 'Sending…' : 'Send Test' }}
                    </button>
                    <div v-if="testResult" :class="testResult.success ? 'text-emerald-400' : 'text-red-400'" class="mt-2 text-xs">
                        {{ testResult.message }}
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
