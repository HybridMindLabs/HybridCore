<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import type Highcharts from 'highcharts';

const props = defineProps<{
    dark: boolean;
    data: number[];
}>();

const container = ref<HTMLElement | null>(null);
let chart: Highcharts.Chart | null = null;
let highcharts: typeof Highcharts | null = null;

function getOptions(dark: boolean): Highcharts.Options {
    const data = props.data;
    const lineColor = '#5b7cff';
    const fillStart = dark ? 'rgba(91,124,255,0.22)' : 'rgba(37,99,235,0.16)';
    const fillEnd = dark ? 'rgba(91,124,255,0)' : 'rgba(37,99,235,0)';

    return {
        chart: {
            type: 'area',
            backgroundColor: 'transparent',
            height: 62,
            margin: [2, 2, 2, 2],
            spacing: [0, 0, 0, 0],
            animation: false,
        },
        title: { text: undefined },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: {
            visible: false,
            categories: data.map((_, index) => `${index}h`),
        },
        yAxis: {
            visible: false,
            min: 0,
        },
        tooltip: {
            backgroundColor: dark ? '#111113' : '#ffffff',
            borderColor: dark ? '#27272a' : '#e2e8f0',
            borderRadius: 8,
            borderWidth: 1,
            shadow: false,
            style: { color: dark ? '#f1f5f9' : '#0f172a', fontSize: '11px' },
            formatter(this: Highcharts.TooltipFormatterContextObject) {
                return `<span style="color:${lineColor}">●</span> <b>${(this.y as number).toLocaleString()}</b> players`;
            },
            useHTML: true,
        },
        plotOptions: {
            area: {
                animation: false,
                marker: {
                    enabled: true,
                    symbol: 'circle',
                    radius: 2,
                    lineWidth: 0,
                    fillColor: lineColor,
                    states: { hover: { enabled: true, radius: 3 } },
                },
                lineWidth: 2,
                lineColor,
                fillColor: {
                    linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                    stops: [
                        [0, fillStart],
                        [1, fillEnd],
                    ],
                },
                states: { hover: { lineWidth: 2 } },
            },
            series: {
                animation: false,
            },
        },
        series: [{
            type: 'area',
            name: 'Players',
            data,
        }],
    };
}

async function init() {
    if (!container.value) return;

    const module = await import('highcharts');
    highcharts = module.default;
    chart = highcharts.chart(container.value, getOptions(props.dark));
}

function destroy() {
    chart?.destroy();
    chart = null;
}

watch(() => props.dark, (dark) => {
    chart?.update(getOptions(dark), true, false, false);
});

onMounted(init);
onUnmounted(destroy);
</script>

<template>
    <div ref="container" class="h-[62px] w-full" />
</template>
