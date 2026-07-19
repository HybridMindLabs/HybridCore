<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import { Server, Puzzle, Paintbrush, Gamepad2, Clock, ArrowRight } from '@lucide/vue';

defineProps<{ phpVersion: string }>();

const version = computed(() => (usePage().props.app as { version?: string })?.version ?? '');
const isPreRelease = computed(() => version.value.startsWith('0.'));
</script>

<template>
    <Head title="Install HybridCore" />

    <InstallerLayout :current-step="1">

        <div class="text-center mb-9">
            <div class="w-14 h-14 rounded-2xl bg-blue-500 flex items-center justify-center mx-auto mb-5 shadow-xl shadow-blue-500/25">
                <span class="text-white text-lg font-black tracking-tight">HC</span>
            </div>
            <h1 class="text-zinc-50 text-3xl font-black tracking-tight">Set up HybridCore</h1>
            <p class="text-zinc-400 text-sm mt-3 max-w-lg mx-auto leading-relaxed">
                A self-hosted platform for gaming communities — your servers, players, news and store,
                all on infrastructure you own. Setup takes about five minutes.
            </p>
        </div>

        <!-- What you get -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
            <div
                v-for="item in [
                    { icon: Server,     label: 'Server browser',  desc: 'Live player counts, maps and uptime for every game server you run.' },
                    { icon: Gamepad2,   label: 'Player accounts', desc: 'Steam and Discord sign-in, profiles, messages and achievements.' },
                    { icon: Puzzle,     label: 'Extensions',      desc: 'Add voting, staff lists, giveaways and more — or write your own.' },
                    { icon: Paintbrush, label: 'Themes',          desc: 'Restyle the public site without touching the platform itself.' },
                ]"
                :key="item.label"
                class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl p-4 flex items-start gap-3"
            >
                <div class="w-8 h-8 rounded-lg bg-zinc-900 border border-zinc-800/70 flex items-center justify-center shrink-0">
                    <component :is="item.icon" :size="14" :stroke-width="1.75" class="text-blue-400/80" />
                </div>
                <div class="min-w-0">
                    <p class="text-zinc-200 text-sm font-semibold">{{ item.label }}</p>
                    <p class="text-zinc-500 text-xs mt-1 leading-relaxed">{{ item.desc }}</p>
                </div>
            </div>
        </div>

        <!-- What this version is. Said once, plainly — not stamped on every page. -->
        <div v-if="isPreRelease" class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0">
                    <Clock :size="14" :stroke-width="1.75" class="text-amber-400" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-zinc-200 text-sm font-semibold">Version {{ version }}</p>
                        <span class="text-amber-400/90 text-[10px] font-semibold uppercase tracking-wider bg-amber-500/10 border border-amber-500/20 rounded px-1.5 py-0.5">Pre-release</span>
                    </div>
                    <p class="text-zinc-500 text-xs mt-1.5 leading-relaxed">
                        This is a 0.x release: everything here works and is covered by tests, but features and
                        extension APIs can still change between versions, and upgrades may need manual steps.
                        Worth reading the release notes before updating a live community.
                    </p>
                </div>
            </div>
        </div>

        <!-- Steps ahead: no surprises about what will be asked for -->
        <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl p-5 mb-6">
            <h3 class="text-zinc-300 text-sm font-bold mb-3">What happens next</h3>
            <ol class="space-y-2.5">
                <li
                    v-for="(step, i) in [
                        'We check this server has everything HybridCore needs.',
                        'You enter your database details — we test them before moving on.',
                        'You create the first administrator account.',
                        'You name your site and pick its language and timezone.',
                        'We write the configuration and set up the database.',
                    ]"
                    :key="i"
                    class="flex items-start gap-3"
                >
                    <span class="w-4 h-4 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-500 text-[9px] font-bold flex items-center justify-center shrink-0 mt-0.5">{{ i + 1 }}</span>
                    <span class="text-zinc-500 text-xs leading-relaxed">{{ step }}</span>
                </li>
            </ol>
            <p class="text-zinc-500 text-xs mt-4 pt-3.5 border-t border-zinc-800/60 leading-relaxed">
                Nothing is written to your server until the final step, and you can go back at any point.
                Have your database name, user and password to hand — your host's control panel has them.
            </p>
        </div>

        <div class="flex items-center justify-between gap-4">
            <p class="text-zinc-500 text-xs">Running PHP <span class="text-zinc-400 font-mono">{{ phpVersion }}</span></p>
            <Link
                :href="route('installer.requirements')"
                class="inline-flex items-center gap-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20"
            >
                Get started
                <ArrowRight :size="14" :stroke-width="2.25" />
            </Link>
        </div>

    </InstallerLayout>
</template>
