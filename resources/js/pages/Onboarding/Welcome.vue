<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';
import { User, MapPin, FileText, Gamepad2, Check, ChevronRight, ChevronLeft, Sparkles, Users } from '@lucide/vue';

interface Game { id: number; name: string; icon: string | null; color: string | null }
interface SuggestedMember { id: number; username: string; name: string; avatar: string | null; is_online: boolean }

const props = defineProps<{ games: Game[]; suggestedMembers: SuggestedMember[] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const step = ref(1);
const totalSteps = 4;

const form = useForm({
    display_name: '',
    bio: '',
    location: '',
    favourite_games: [] as number[],
    follow_users: [] as number[],
});

function toggleGame(id: number) {
    if (form.favourite_games.includes(id)) {
        form.favourite_games = form.favourite_games.filter((g) => g !== id);
    } else {
        form.favourite_games = [...form.favourite_games, id];
    }
}

function toggleFollow(id: number) {
    if (form.follow_users.includes(id)) {
        form.follow_users = form.follow_users.filter((u) => u !== id);
    } else {
        form.follow_users = [...form.follow_users, id];
    }
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}

function next() { if (step.value < totalSteps) step.value++; }
function prev() { if (step.value > 1) step.value--; }
function skip() { form.post(route('onboarding.complete')); }

function submit() {
    form.post(route('onboarding.complete'));
}

const steps = computed(() => [
    { title: t('onboarding.step1_title'), icon: User },
    { title: t('onboarding.step2_title'), icon: Gamepad2 },
    { title: t('onboarding.step3_title'), icon: FileText },
    { title: t('onboarding.step4_title'), icon: Users },
]);
</script>

<template>
    <Head title="Welcome" />
    <PublicLayout>
        <div class="max-w-xl mx-auto px-4 py-16">

            <!-- Header -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4"
                    :class="dark ? 'bg-blue-500/10 border border-blue-500/20' : 'bg-blue-50 border border-blue-100'">
                    <Sparkles :size="22" :stroke-width="1.8" class="text-blue-400" />
                </div>
                <h1 class="text-[28px] font-black tracking-tight mb-2" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('onboarding.welcome_title') }}
                </h1>
                <p class="text-[14px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('onboarding.welcome_subtitle') }}
                </p>
            </div>

            <!-- Step progress -->
            <div class="flex items-center gap-2 mb-8">
                <template v-for="(s, i) in steps" :key="i">
                    <div class="flex-1 h-1.5 rounded-full transition-all duration-300"
                        :class="i + 1 <= step
                            ? 'bg-blue-500'
                            : (dark ? 'bg-zinc-800' : 'bg-zinc-200')" />
                </template>
            </div>

            <!-- Card -->
            <div class="rounded-2xl border overflow-hidden"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">

                <!-- Step header -->
                <div class="px-6 py-4 border-b flex items-center gap-3"
                    :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <component :is="steps[step-1].icon" :size="15" :stroke-width="1.8" class="text-blue-400" />
                    </div>
                    <div>
                        <p class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ steps[step-1].title }}</p>
                        <p class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('onboarding.step_of', { current: step, total: totalSteps }) }}</p>
                    </div>
                </div>

                <!-- Step 1: display_name -->
                <div v-if="step === 1" class="p-6 space-y-4">
                    <div>
                        <label class="text-[12px] font-semibold uppercase tracking-wide mb-1.5 block"
                            :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('onboarding.display_name') }} <span class="text-zinc-700 normal-case tracking-normal font-normal">{{ t('onboarding.optional') }}</span>
                        </label>
                        <input v-model="form.display_name" type="text" :placeholder="t('onboarding.display_name_placeholder')" maxlength="50"
                            class="w-full rounded-xl border px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            :class="dark ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500' : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400'" />
                        <p class="text-[11px] mt-1.5" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                            {{ t('onboarding.display_name_hint') }}
                        </p>
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold uppercase tracking-wide mb-1.5 block"
                            :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('onboarding.location') }} <span class="text-zinc-700 normal-case tracking-normal font-normal">{{ t('onboarding.optional') }}</span>
                        </label>
                        <div class="relative">
                            <MapPin :size="14" :stroke-width="1.8" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600" />
                            <input v-model="form.location" type="text" :placeholder="t('onboarding.location_placeholder')" maxlength="100"
                                class="w-full rounded-xl border pl-9 pr-4 py-2.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                :class="dark ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500' : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400'" />
                        </div>
                    </div>
                </div>

                <!-- Step 2: games -->
                <div v-else-if="step === 2" class="p-6">
                    <p v-if="!games.length" class="text-zinc-500 text-sm text-center py-4">{{ t('onboarding.no_games') }}</p>
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <button v-for="g in games" :key="g.id" type="button"
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border text-left transition-all"
                            :class="form.favourite_games.includes(g.id)
                                ? 'border-blue-500/60 bg-blue-500/10 text-blue-300'
                                : (dark ? 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:border-zinc-700' : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-zinc-300')"
                            @click="toggleGame(g.id)">
                            <img v-if="g.icon" :src="g.icon" class="w-5 h-5 rounded object-cover shrink-0" />
                            <span class="text-[13px] font-semibold flex-1 truncate">{{ g.name }}</span>
                            <Check v-if="form.favourite_games.includes(g.id)" :size="13" :stroke-width="2.5" class="shrink-0 text-blue-400" />
                        </button>
                    </div>
                </div>

                <!-- Step 3: bio -->
                <div v-else-if="step === 3" class="p-6">
                    <label class="text-[12px] font-semibold uppercase tracking-wide mb-1.5 block"
                        :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ t('onboarding.bio') }} <span class="text-zinc-700 normal-case tracking-normal font-normal">{{ t('onboarding.optional') }}</span>
                    </label>
                    <textarea v-model="form.bio" rows="5" maxlength="500" :placeholder="t('onboarding.bio_placeholder')"
                        class="w-full rounded-xl border px-4 py-3 text-sm resize-none transition focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                        :class="dark ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500' : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400'" />
                    <p class="text-[11px] mt-1 text-right" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                        {{ form.bio.length }}/500
                    </p>
                </div>

                <!-- Step 4: follow members -->
                <div v-else-if="step === 4" class="p-6">
                    <p v-if="!suggestedMembers.length" class="text-zinc-500 text-sm text-center py-4">
                        {{ t('onboarding.no_members') }}
                    </p>
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <button v-for="m in suggestedMembers" :key="m.id" type="button"
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border text-left transition-all"
                            :class="form.follow_users.includes(m.id)
                                ? 'border-blue-500/60 bg-blue-500/10 text-blue-300'
                                : (dark ? 'border-zinc-800 bg-zinc-900/60 text-zinc-400 hover:border-zinc-700' : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-zinc-300')"
                            @click="toggleFollow(m.id)">
                            <span class="relative w-6 h-6 rounded-lg overflow-hidden shrink-0">
                                <img v-if="m.avatar" :src="m.avatar" class="w-full h-full object-cover" :alt="m.username" />
                                <span v-else class="w-full h-full flex items-center justify-center text-[10px] font-black text-white uppercase"
                                    :style="{ backgroundColor: avatarBg(m.name) }">{{ m.name[0] }}</span>
                                <span v-if="m.is_online" class="absolute bottom-0 right-0 w-1.5 h-1.5 rounded-full bg-emerald-400" />
                            </span>
                            <span class="text-[13px] font-semibold flex-1 truncate">{{ m.username }}</span>
                            <Check v-if="form.follow_users.includes(m.id)" :size="13" :stroke-width="2.5" class="shrink-0 text-blue-400" />
                        </button>
                    </div>
                    <p v-if="suggestedMembers.length" class="text-[11px] mt-3" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                        {{ t('onboarding.follow_hint') }}
                    </p>
                </div>

                <!-- Footer nav -->
                <div class="px-6 py-4 border-t flex items-center gap-3"
                    :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                    <button v-if="step > 1" type="button"
                        class="flex items-center gap-1.5 text-[13px] font-semibold px-4 py-2.5 rounded-xl border transition"
                        :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-500 hover:text-zinc-700'"
                        @click="prev">
                        <ChevronLeft :size="14" :stroke-width="2" /> {{ t('onboarding.back') }}
                    </button>
                    <button type="button"
                        class="ml-auto text-[12px] font-medium px-3 py-2 rounded-lg transition"
                        :class="dark ? 'text-zinc-600 hover:text-zinc-400' : 'text-zinc-400 hover:text-zinc-600'"
                        @click="skip">
                        {{ t('onboarding.skip_all') }}
                    </button>
                    <button v-if="step < totalSteps" type="button"
                        class="flex items-center gap-1.5 text-[13px] font-bold px-5 py-2.5 rounded-xl bg-blue-500 text-white hover:bg-blue-600 transition-colors"
                        @click="next">
                        {{ t('onboarding.next') }} <ChevronRight :size="14" :stroke-width="2.5" />
                    </button>
                    <button v-else type="button"
                        :disabled="form.processing"
                        class="flex items-center gap-1.5 text-[13px] font-bold px-5 py-2.5 rounded-xl bg-emerald-500 text-white hover:bg-emerald-600 transition-colors disabled:opacity-50"
                        @click="submit">
                        <Check :size="14" :stroke-width="2.5" /> {{ t('onboarding.finish') }}
                    </button>
                </div>
            </div>

        </div>
    </PublicLayout>
</template>
