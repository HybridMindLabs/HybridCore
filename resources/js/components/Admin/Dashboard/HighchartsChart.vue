<script setup lang="ts">
import type Highcharts from 'highcharts';
import { onMounted, onUnmounted, ref, watch } from 'vue';

/**
 * One Highcharts chart. The library is loaded on first use and shared, and the
 * chart is rebuilt whenever its options change (new data, theme switch).
 */
const props = defineProps<{ options: Highcharts.Options; label: string }>();

let lib: Promise<typeof Highcharts> | null = null;
const loadHighcharts = () => (lib ??= import('highcharts').then((m) => m.default));

const el = ref<HTMLElement | null>(null);
let chart: Highcharts.Chart | null = null;
let alive = true;

async function render() {
    const hc = await loadHighcharts();
    if (!alive || !el.value) return;
    chart?.destroy();
    chart = hc.chart(el.value, props.options);
}

onMounted(render);
watch(() => props.options, render);
onUnmounted(() => {
    alive = false;
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div ref="el" role="img" :aria-label="label" />
</template>
