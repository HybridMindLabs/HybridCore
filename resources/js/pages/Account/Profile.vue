<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';
import { Camera, Trash2, Upload, Lock, Globe, Users } from '@lucide/vue';

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

const form = useForm({
    username:        props.account.username,
    display_name:    props.account.display_name ?? '',
    email:           props.account.email,
    bio:             props.account.bio ?? '',
    location:        props.account.location ?? '',
    website:         props.account.website ?? '',
    profile_privacy: props.account.profile_privacy,
}).withPrecognition('put', route('account.profile.update'));

const avatarForm = useForm({ avatar: null as File | null });
const bannerForm = useForm({ banner: null as File | null });
const avatarPreview = ref<string | null>(props.account.avatar);
const bannerPreview = ref<string | null>(props.account.banner);

const input = computed(() => dark.value
    ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
    : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] placeholder:text-zinc-400 focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition',
);
const label = computed(() => dark.value
    ? 'text-zinc-500 text-[11px] font-bold uppercase tracking-widest'
    : 'text-zinc-400 text-[11px] font-bold uppercase tracking-widest',
);

const privacyOptions = [
    { value: 'public',  label: 'Public',      desc: 'Anyone can see',       icon: Globe },
    { value: 'members', label: 'Members only', desc: 'Only logged-in users', icon: Users },
    { value: 'private', label: 'Private',      desc: 'Only you',             icon: Lock  },
] as const;

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}
function onAvatarPick(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    avatarForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
    avatarForm.post(route('account.avatar.upload'), {});
}
function onBannerPick(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    bannerForm.banner = file;
    bannerPreview.value = URL.createObjectURL(file);
    bannerForm.post(route('account.banner.upload'), {});
}
function deleteAvatar() {
    router.delete(route('account.avatar.delete'), { onSuccess: () => { avatarPreview.value = null; } });
}
function deleteBanner() {
    router.delete(route('account.banner.delete'), { onSuccess: () => { bannerPreview.value = null; } });
}
function submit() { form.submit(); }
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
            <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">Profile Banner</p>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Top of your public profile. Recommended 1200x300px.</p>
            </div>
            <div class="p-6">
                <div class="relative w-full h-32 rounded-xl overflow-hidden mb-4 border" :class="dark ? 'border-zinc-800 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'">
                    <img v-if="bannerPreview" :src="bannerPreview" class="w-full h-full object-cover" alt="Banner" />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <p class="text-[12px]" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">No banner set</p>
                    </div>
                    <label class="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 hover:opacity-100 transition cursor-pointer text-white text-[13px] font-semibold">
                        <Upload :size="15" :stroke-width="2" /> Upload banner
                        <input type="file" class="hidden" accept="image/jpeg,image/png,image/webp" @change="onBannerPick" />
                    </label>
                </div>
                <div class="flex items-center gap-2">
                    <label class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-[12px] font-semibold cursor-pointer transition" :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-600 hover:border-zinc-300'">
                        <Camera :size="13" :stroke-width="2" /> Change banner
                        <input type="file" class="hidden" accept="image/jpeg,image/png,image/webp" @change="onBannerPick" />
                    </label>
                    <button v-if="bannerPreview" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-[12px] font-semibold transition" :class="dark ? 'border-red-500/30 text-red-400 hover:bg-red-500/10' : 'border-red-200 text-red-600 hover:bg-red-50'" @click="deleteBanner">
                        <Trash2 :size="13" :stroke-width="2" /> Remove
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
            <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('account.profile') }}</p>
            </div>
            <div class="p-6 flex flex-col gap-5">
                <div class="flex items-center gap-5">
                    <div class="relative shrink-0">
                        <div class="w-20 h-20 rounded-2xl overflow-hidden border-2" :class="dark ? 'border-zinc-800' : 'border-zinc-200'">
                            <img v-if="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" :alt="account.username" />
                            <div v-else class="w-full h-full flex items-center justify-center text-[28px] font-black text-white uppercase" :style="{ backgroundColor: avatarBg(account.username) }">{{ account.username[0] }}</div>
                        </div>
                        <label class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full flex items-center justify-center cursor-pointer border-2" :class="dark ? 'bg-blue-500 hover:bg-blue-600 border-[#111113]' : 'bg-blue-500 hover:bg-blue-600 border-white'">
                            <Camera :size="12" :stroke-width="2.5" class="text-white" />
                            <input type="file" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif" @change="onAvatarPick" />
                        </label>
                    </div>
                    <div class="flex-1">
                        <p class="text-[13px] font-semibold mb-0.5" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">Profile picture</p>
                        <p class="text-[11px] mb-2" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">JPG, PNG, WebP or GIF · Max 2MB · Resized to 256x256</p>
                        <div class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-semibold cursor-pointer transition" :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-600 hover:border-zinc-300'">
                                <Upload :size="11" /> Upload
                                <input type="file" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif" @change="onAvatarPick" />
                            </label>
                            <button v-if="avatarPreview" type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-semibold transition" :class="dark ? 'border-red-500/30 text-red-400 hover:bg-red-500/10' : 'border-red-200 text-red-600 hover:bg-red-50'" @click="deleteAvatar">
                                <Trash2 :size="11" /> Remove
                            </button>
                        </div>
                    </div>
                </div>
                <div class="h-px" :class="dark ? 'bg-zinc-800/60' : 'bg-zinc-100'" />
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <div class="flex flex-col gap-2">
                        <label :class="label">Username <span v-if="!account.can_change_username" class="normal-case font-normal tracking-normal ml-2 text-amber-500 text-[11px]">— next change: {{ account.username_change_available_at }}</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-[14px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">@</span>
                            <input v-model="form.username" type="text" placeholder="your_username" @change="form.validate('username')" :disabled="!account.can_change_username" :class="[input, 'pl-7 font-mono', form.errors.username ? '!border-red-500/60' : '', !account.can_change_username ? 'opacity-50 cursor-not-allowed' : '']" />
                        </div>
                        <p v-if="form.errors.username" class="text-red-400 text-[12px] font-semibold">{{ form.errors.username }}</p>
                        <p v-else class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">3-32 chars, letters/numbers/underscores/hyphens. Changeable once every 30 days.</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label :class="label">Display name <span class="normal-case font-normal tracking-normal" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">— optional</span></label>
                        <input v-model="form.display_name" type="text" placeholder="Shown instead of @username" :class="[input, form.errors.display_name ? '!border-red-500/60' : '']" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label :class="label">{{ t('account.email') }} <span v-if="!account.verified" class="text-amber-500 normal-case ml-1 font-semibold tracking-normal">{{ t('account.email_unverified') }}</span></label>
                        <input v-model="form.email" type="email" @change="form.validate('email')" :class="[input, form.errors.email ? '!border-red-500/60' : '']" />
                        <p v-if="form.errors.email" class="text-red-400 text-[12px] font-semibold">{{ form.errors.email }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label :class="label">{{ t('account.bio') }} <span class="normal-case font-normal tracking-normal" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">— optional</span></label>
                        <textarea v-model="form.bio" rows="3" maxlength="500" class="resize-none" :class="input" />
                        <p class="text-[11px] text-right" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ form.bio.length }}/500</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="flex flex-col gap-2">
                            <label :class="label">{{ t('account.location') }} <span class="normal-case font-normal tracking-normal" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">— optional</span></label>
                            <input v-model="form.location" type="text" :class="input" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label :class="label">{{ t('account.website') }} <span class="normal-case font-normal tracking-normal" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">— optional</span></label>
                            <input v-model="form.website" type="url" placeholder="https://example.com" @change="form.validate('website')" :class="input" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label :class="label">Profile visibility</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <label v-for="opt in privacyOptions" :key="opt.value" class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition" :class="form.profile_privacy === opt.value ? (dark ? 'border-blue-500/40 bg-blue-500/8' : 'border-blue-300 bg-blue-50') : (dark ? 'border-zinc-800 hover:border-zinc-700' : 'border-zinc-200 hover:border-zinc-300')">
                                <input type="radio" v-model="form.profile_privacy" :value="opt.value" class="hidden" />
                                <component :is="opt.icon" :size="15" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="form.profile_privacy === opt.value ? 'text-blue-400' : (dark ? 'text-zinc-600' : 'text-zinc-400')" />
                                <div>
                                    <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ opt.label }}</p>
                                    <p class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ opt.desc }}</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                        <button type="submit" :disabled="form.processing" class="bg-blue-500 hover:bg-blue-600 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl transition disabled:opacity-60 shadow-md shadow-blue-500/20">
                            {{ t('account.save_profile') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
