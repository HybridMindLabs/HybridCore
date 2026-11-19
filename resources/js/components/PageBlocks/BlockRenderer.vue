<script setup lang="ts">
import { resolveDynamicComponent, type Component } from 'vue';
import PageBlockHero from './PageBlockHero.vue';
import PageBlockText from './PageBlockText.vue';
import PageBlockImage from './PageBlockImage.vue';
import PageBlockGallery from './PageBlockGallery.vue';
import PageBlockColumns from './PageBlockColumns.vue';
import PageBlockServers from './PageBlockServers.vue';
import PageBlockNews from './PageBlockNews.vue';
import PageBlockFaq from './PageBlockFaq.vue';
import PageBlockCta from './PageBlockCta.vue';
import PageBlockVideo from './PageBlockVideo.vue';
import PageBlockDivider from './PageBlockDivider.vue';
import type { RenderedBlock } from './types';

defineProps<{ blocks: RenderedBlock[] }>();

const CORE: Record<string, Component> = {
    PageBlockHero, PageBlockText, PageBlockImage, PageBlockGallery, PageBlockColumns,
    PageBlockServers, PageBlockNews, PageBlockFaq, PageBlockCta, PageBlockVideo, PageBlockDivider,
};

// Extension blocks are globally registered components (see app.ts); an
// unknown name resolves to a string and is skipped instead of rendering junk.
function resolve(name: string): Component | null {
    if (CORE[name]) return CORE[name];
    const found = resolveDynamicComponent(name);
    return typeof found === 'string' ? null : (found as Component);
}
</script>

<template>
    <div class="flex flex-col gap-10">
        <template v-for="(block, index) in blocks" :key="block.id">
            <section v-if="resolve(block.component)" :data-block="block.type">
                <component :is="resolve(block.component)!" :data="block.data" :props="block.props" v-bind="block.type === 'hero' ? { index } : {}" />
            </section>
        </template>
    </div>
</template>
