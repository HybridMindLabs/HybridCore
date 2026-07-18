<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Camera, Check, Globe, Info, Lock, Trash2, Upload, Users } from '@lucide/vue';

const props = defineProps<{
    account: {
        username: string;
        display_name: string | null;
        email: string;
        avatar: string | null;
        banner: string | null;
        bio: string | null;
        location: string | null;
        website: string | null;
        profile_privacy: 'public' | 'members' | 'private';
        verified: boolean;
        can_change_username: boolean;
        username_change_available_at: string | null;
    };
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const BIO_MAX = 500;

const form = useForm({
    username: props.account.username,
    display_name: props.account.display_name ?? '',
    email: props.account.email,
    bio: props.account.bio ?? '',
    location: props.account.location ?? '',
    website: props.account.website ?? '',
    profile_privacy: props.account.profile_privacy,
}).withPrecognition('put', route('account.profile.update'));

const avatarForm = useForm({ avatar: null as File | null });
const bannerForm = useForm({ banner: null as File | null });
const avatarPreview = ref<string | null>(props.account.avatar);
const bannerPreview = ref<string | null>(props.account.banner);

/**
 * Object URLs have to be released by hand or every pick leaks the blob for the
 * lifetime of the page. Tracked so a rejected upload can also roll its preview
 * back — it used to keep showing the new image after the server refused it,
 * with the error never rendered anywhere.
 */
const objectUrls: string[] = [];

function previewFor(file: File): string {
    const url = URL.createObjectURL(file);
    objectUrls.push(url);

    return url;
}

onBeforeUnmount(() => objectUrls.forEach((url) => URL.revokeObjectURL(url)));

function onAvatarPick(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    const previous = avatarPreview.value;
    avatarForm.avatar = file;
    avatarPreview.value = previewFor(file);

    avatarForm.post(route('account.avatar.upload'), {
        onError: () => {
            avatarPreview.value = previous;
        },
        onFinish: () => {
            target.value = '';
        },
    });
}

function onBannerPick(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    const previous = bannerPreview.value;
    bannerForm.banner = file;
    bannerPreview.value = previewFor(file);

    bannerForm.post(route('account.banner.upload'), {
        onError: () => {
            bannerPreview.value = previous;
        },
        onFinish: () => {
            target.value = '';
        },
    });
}

function deleteAvatar() {
    router.delete(route('account.avatar.delete'), {
        onSuccess: () => {
            avatarPreview.value = null;
        },
    });
}

function deleteBanner() {
    router.delete(route('account.banner.delete'), {
        onSuccess: () => {
            bannerPreview.value = null;
        },
    });
}

const emailChanged = computed(() => form.email !== props.account.email);

const privacyOptions = computed(() => [
    {
        value: 'public',
        label: t('account.pf_visibility_public'),
        desc: t('account.pf_visibility_public_desc'),
        icon: Globe,
    },
    {
        value: 'members',
        label: t('account.pf_visibility_members'),
        desc: t('account.pf_visibility_members_desc'),
        icon: Users,
    },
    {
        value: 'private',
        label: t('account.pf_visibility_private'),
        desc: t('account.pf_visibility_private_desc'),
        icon: Lock,
    },
]);

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];

    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}

function submit() {
    form.submit();
}

const card = computed(() =>
    dark.value ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]',
);
const cardHead = computed(() => (dark.value ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'));
const input = computed(() =>
    dark.value
        ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
        : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] placeholder:text-zinc-400 focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition',
);
const label = computed(() =>
    dark.value
        ? 'text-zinc-400 text-[11px] font-bold uppercase tracking-widest'
        : 'text-zinc-500 text-[11px] font-bold uppercase tracking-widest',
);
const hint = computed(() => (dark.value ? 'text-zinc-500 text-[11px]' : 'text-zinc-500 text-[11px]'));
const ghostBtn = computed(() =>
    dark.value
        ? 'border-zinc-800 text-zinc-300 hover:text-zinc-100 hover:border-zinc-600'
        : 'border-zinc-300 text-zinc-700 hover:border-zinc-400 hover:bg-zinc-50',
);
const dangerBtn = computed(() =>
    dark.value ? 'border-red-500/30 text-red-400 hover:bg-red-500/10' : 'border-red-300 text-red-700 hover:bg-red-50',
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Banner -->
        <div class="rounded-2xl border overflow-hidden" :class="card">
            <div class="px-6 py-4 border-b" :class="cardHead">
                <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.pf_banner_title') }}
                </h2>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.pf_banner_subtitle') }}
                </p>
            </div>
            <div class="p-6">
                <div
                    class="relative w-full h-32 rounded-xl overflow-hidden mb-4 border"
                    :class="dark ? 'border-zinc-800 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'"
                >
                    <img v-if="bannerPreview" :src="bannerPreview" class="w-full h-full object-cover" alt="" />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <p class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('account.pf_banner_empty') }}
                        </p>
                    </div>
                </div>

                <p v-if="bannerForm.errors.banner" role="alert" class="text-red-500 text-[12px] font-semibold mb-3">
                    {{ bannerForm.errors.banner }}
                </p>

                <div class="flex items-center gap-2">
                    <label
                        for="banner_input"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-[12px] font-semibold cursor-pointer transition
                               focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-blue-500"
                        :class="ghostBtn"
                    >
                        <Camera :size="13" :stroke-width="2" />
                        {{ bannerPreview ? t('account.pf_banner_change') : t('account.pf_banner_upload') }}
                        <input
                            id="banner_input"
                            type="file"
                            class="sr-only"
                            accept="image/jpeg,image/png,image/webp"
                            @change="onBannerPick"
                        />
                    </label>
                    <button
                        v-if="bannerPreview"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-[12px] font-semibold transition
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                        :class="dangerBtn"
                        @click="deleteBanner"
                    >
                        <Trash2 :size="13" :stroke-width="2" />
                        {{ t('account.pf_remove') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Profile -->
        <div class="rounded-2xl border overflow-hidden" :class="card">
            <div class="px-6 py-4 border-b" :class="cardHead">
                <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.profile') }}
                </h2>
            </div>

            <div class="p-6 flex flex-col gap-5">
                <div class="flex items-center gap-5">
                    <div class="relative shrink-0">
                        <div class="w-20 h-20 rounded-2xl overflow-hidden border-2" :class="dark ? 'border-zinc-800' : 'border-zinc-200'">
                            <img v-if="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" alt="" />
                            <div
                                v-else
                                class="w-full h-full flex items-center justify-center text-[28px] font-black text-white uppercase"
                                :style="{ backgroundColor: avatarBg(account.username) }"
                                aria-hidden="true"
                            >
                                {{ account.username[0] }}
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-[13px] font-bold mb-0.5" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">
                            {{ t('account.pf_avatar_title') }}
                        </h3>
                        <p class="mb-2" :class="hint">{{ t('account.pf_avatar_hint') }}</p>

                        <p v-if="avatarForm.errors.avatar" role="alert" class="text-red-500 text-[12px] font-semibold mb-2">
                            {{ avatarForm.errors.avatar }}
                        </p>

                        <div class="flex items-center gap-2">
                            <label
                                for="avatar_input"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-semibold cursor-pointer transition
                                       focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-blue-500"
                                :class="ghostBtn"
                            >
                                <Upload :size="11" />
                                {{ t('account.pf_avatar_upload') }}
                                <input
                                    id="avatar_input"
                                    type="file"
                                    class="sr-only"
                                    accept="image/jpeg,image/png,image/webp,image/gif"
                                    @change="onAvatarPick"
                                />
                            </label>
                            <button
                                v-if="avatarPreview"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-semibold transition
                                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                                :class="dangerBtn"
                                @click="deleteAvatar"
                            >
                                <Trash2 :size="11" />
                                {{ t('account.pf_remove') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="h-px" :class="dark ? 'bg-zinc-800/60' : 'bg-zinc-100'" />

                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <div class="flex flex-col gap-2">
                        <label for="pf_username" :class="label">{{ t('account.username') }}</label>
                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-[14px]"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-400'"
                                aria-hidden="true"
                                >@</span
                            >
                            <input
                                id="pf_username"
                                v-model="form.username"
                                type="text"
                                autocomplete="username"
                                :placeholder="t('account.pf_username_placeholder')"
                                :disabled="!account.can_change_username"
                                :aria-invalid="!!form.errors.username"
                                :aria-describedby="form.errors.username ? 'pf_username_error' : 'pf_username_hint'"
                                :class="[
                                    input,
                                    'pl-7 font-mono',
                                    form.errors.username ? '!border-red-500/60' : '',
                                    !account.can_change_username ? 'opacity-60 cursor-not-allowed' : '',
                                ]"
                                @change="form.validate('username')"
                            />
                        </div>
                        <p v-if="form.errors.username" id="pf_username_error" class="text-red-500 text-[12px] font-semibold">
                            {{ form.errors.username }}
                        </p>
                        <p v-else id="pf_username_hint" :class="hint">
                            <span v-if="!account.can_change_username" class="font-semibold" :class="dark ? 'text-amber-400' : 'text-amber-700'">
                                {{ t('account.pf_username_next_change', { date: account.username_change_available_at }) }}
                            </span>
                            <span v-else>{{ t('account.pf_username_hint') }}</span>
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="pf_display_name" :class="label">
                            {{ t('account.pf_display_name') }}
                            <span class="normal-case font-normal tracking-normal" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                — {{ t('account.pf_optional') }}
                            </span>
                        </label>
                        <input
                            id="pf_display_name"
                            v-model="form.display_name"
                            type="text"
                            :placeholder="t('account.pf_display_name_placeholder')"
                            :class="[input, form.errors.display_name ? '!border-red-500/60' : '']"
                        />
                        <p v-if="form.errors.display_name" class="text-red-500 text-[12px] font-semibold">
                            {{ form.errors.display_name }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="pf_email" :class="label">
                            {{ t('account.email') }}
                            <span
                                v-if="!account.verified"
                                class="normal-case ml-1 font-semibold tracking-normal"
                                :class="dark ? 'text-amber-400' : 'text-amber-700'"
                            >
                                {{ t('account.email_unverified') }}
                            </span>
                        </label>
                        <input
                            id="pf_email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            :aria-invalid="!!form.errors.email"
                            :aria-describedby="form.errors.email ? 'pf_email_error' : undefined"
                            :class="[input, form.errors.email ? '!border-red-500/60' : '']"
                            @change="form.validate('email')"
                        />
                        <p v-if="form.errors.email" id="pf_email_error" class="text-red-500 text-[12px] font-semibold">
                            {{ form.errors.email }}
                        </p>
                        <!-- The controller clears email_verified_at on change; say so before saving, not after. -->
                        <p
                            v-else-if="emailChanged"
                            class="inline-flex items-start gap-1.5 text-[12px] leading-relaxed"
                            :class="dark ? 'text-amber-300' : 'text-amber-800'"
                        >
                            <Info :size="13" :stroke-width="2" class="mt-0.5 shrink-0" />
                            {{ t('account.pf_email_change_note') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="pf_bio" :class="label">
                            {{ t('account.bio') }}
                            <span class="normal-case font-normal tracking-normal" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                — {{ t('account.pf_optional') }}
                            </span>
                        </label>
                        <textarea
                            id="pf_bio"
                            v-model="form.bio"
                            rows="3"
                            :maxlength="BIO_MAX"
                            aria-describedby="pf_bio_counter"
                            class="resize-none"
                            :class="input"
                        />
                        <p id="pf_bio_counter" class="text-right" :class="hint">
                            {{ t('account.pf_bio_counter', { count: form.bio.length, max: BIO_MAX }) }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="flex flex-col gap-2">
                            <label for="pf_location" :class="label">
                                {{ t('account.location') }}
                                <span class="normal-case font-normal tracking-normal" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                    — {{ t('account.pf_optional') }}
                                </span>
                            </label>
                            <input id="pf_location" v-model="form.location" type="text" :class="input" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="pf_website" :class="label">
                                {{ t('account.website') }}
                                <span class="normal-case font-normal tracking-normal" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                    — {{ t('account.pf_optional') }}
                                </span>
                            </label>
                            <input
                                id="pf_website"
                                v-model="form.website"
                                type="url"
                                placeholder="https://example.com"
                                :aria-invalid="!!form.errors.website"
                                :class="[input, form.errors.website ? '!border-red-500/60' : '']"
                                @change="form.validate('website')"
                            />
                            <p v-if="form.errors.website" class="text-red-500 text-[12px] font-semibold">
                                {{ form.errors.website }}
                            </p>
                        </div>
                    </div>

                    <!-- The radios were class="hidden", which drops them out of
                         the accessibility tree — the setting could not be
                         reached or read at all without a mouse. sr-only keeps
                         them focusable. -->
                    <fieldset class="flex flex-col gap-2">
                        <legend :class="label">{{ t('account.pf_visibility') }}</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <label
                                v-for="opt in privacyOptions"
                                :key="opt.value"
                                class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition
                                       has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-blue-500"
                                :class="form.profile_privacy === opt.value
                                    ? dark ? 'border-blue-500/40 bg-blue-500/10' : 'border-blue-300 bg-blue-50'
                                    : dark ? 'border-zinc-800 hover:border-zinc-700' : 'border-zinc-200 hover:border-zinc-300'"
                            >
                                <input v-model="form.profile_privacy" type="radio" :value="opt.value" class="sr-only" />
                                <component
                                    :is="opt.icon"
                                    :size="15"
                                    :stroke-width="1.8"
                                    aria-hidden="true"
                                    class="mt-0.5 shrink-0"
                                    :class="form.profile_privacy === opt.value
                                        ? dark ? 'text-blue-400' : 'text-blue-600'
                                        : dark ? 'text-zinc-500' : 'text-zinc-400'"
                                />
                                <div>
                                    <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ opt.label }}</p>
                                    <p class="text-[11px] leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ opt.desc }}</p>
                                </div>
                            </label>
                        </div>
                    </fieldset>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                        <Transition
                            enter-active-class="transition duration-200"
                            enter-from-class="opacity-0 translate-y-1"
                            leave-active-class="transition duration-200"
                            leave-to-class="opacity-0"
                        >
                            <span
                                v-if="form.recentlySuccessful"
                                role="status"
                                class="inline-flex items-center gap-1 text-[12px] font-semibold"
                                :class="dark ? 'text-emerald-400' : 'text-emerald-600'"
                            >
                                <Check :size="13" :stroke-width="2.4" />
                                {{ t('account.pf_saved') }}
                            </span>
                        </Transition>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl
                                   transition disabled:opacity-60 disabled:cursor-not-allowed shadow-md shadow-blue-500/20
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                        >
                            {{ t('account.save_profile') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
