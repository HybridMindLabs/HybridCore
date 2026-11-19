<script setup lang="ts">
import { inputClass, type SettingsForm } from '../settingsForm';

defineProps<{ form: SettingsForm }>();

const socials = [
    { key: 'social_discord', label: 'Discord invite URL', placeholder: 'https://discord.gg/...' },
    { key: 'social_steam',   label: 'Steam group URL',    placeholder: 'https://steamcommunity.com/groups/...' },
    { key: 'social_twitter', label: 'X / Twitter URL',    placeholder: 'https://x.com/...' },
    { key: 'social_youtube', label: 'YouTube URL',        placeholder: 'https://youtube.com/@...' },
] as const;
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
            <h3 class="text-zinc-100 text-sm font-semibold">Search Engine Optimization</h3>
            <p class="text-zinc-600 text-xs -mt-2">These values are used as fallbacks when a page doesn't define its own metadata.</p>

            <div class="flex flex-col gap-1.5">
                <label for="set-seo-title" class="text-zinc-400 text-xs font-medium">Site title <span class="text-zinc-600 font-normal">— appended to page titles</span></label>
                <input id="set-seo-title" v-model="form.seo_site_title" type="text" placeholder="My Platform" :class="inputClass" />
                <p class="text-zinc-600 text-xs">Produces titles like "Blog — My Platform".</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="set-seo-description" class="text-zinc-400 text-xs font-medium">Default meta description</label>
                <textarea id="set-seo-description" v-model="form.seo_meta_description" rows="3" placeholder="A short description of your site shown in search results." :class="[inputClass, 'resize-none']" />
                <p class="text-zinc-600 text-xs" :class="form.seo_meta_description.length > 160 ? '!text-amber-400' : ''">
                    Aim for 150–160 characters ({{ form.seo_meta_description.length }} now).
                </p>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="set-seo-og" class="text-zinc-400 text-xs font-medium">Default OG image URL</label>
                <input id="set-seo-og" v-model="form.seo_og_image" type="url" placeholder="https://example.com/og.png" :class="inputClass" />
                <p class="text-zinc-600 text-xs">Shown when pages are shared on social media. Recommended size: 1200×630 px.</p>
            </div>
        </div>

        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
            <h3 class="text-zinc-100 text-sm font-semibold">Social links</h3>
            <p class="text-zinc-600 text-xs -mt-2">Shown in the site header, footer and quick links. Leave a field empty to hide that platform.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-for="social in socials" :key="social.key" class="flex flex-col gap-1.5">
                    <label :for="`set-${social.key}`" class="text-zinc-400 text-xs font-medium">{{ social.label }}</label>
                    <input :id="`set-${social.key}`" v-model="form[social.key]" type="url" :placeholder="social.placeholder" :class="inputClass" />
                    <p v-if="form.errors[social.key]" class="text-red-400 text-xs">{{ form.errors[social.key] }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
