<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Single-series area chart for "players over time".
 *
 * Hand-drawn SVG rather than a charting library: this renders on a public page,
 * and the alternative on hand (Highcharts) is 278 kB for one line. The forms
 * used across the public pages are simple enough that the library would cost
 * more than it saves.
 *
 * Nulls are gaps, not zeroes. An hour with no snapshot means "we did not look",
 * which is not the same as "nobody was playing", so the line breaks there.
 */
interface Point { t: string; players: number | null }

const props = withDefaults(defineProps<{
    data: Point[];
    dark?: boolean;
    height?: number;
    label: string;
    emptyLabel: string;
}>(), { dark: false, height: 132 });

const W = 600;
const PAD_TOP = 10;
const PAD_BOTTOM = 18;

const H = computed(() => props.height);
const plotHeight = computed(() => H.value - PAD_TOP - PAD_BOTTOM);

const values = computed(() => props.data.map(d => d.players));
const known = computed(() => values.value.filter((v): v is number => v !== null));

/** Round the top of the scale to something readable, never zero-height. */
const maxValue = computed(() => {
    const peak = known.value.length ? Math.max(...known.value) : 0;
    if (peak <= 0) return 4;
    const step = peak <= 10 ? 2 : peak <= 50 ? 10 : peak <= 200 ? 25 : 100;
    return Math.ceil(peak / step) * step;
});

function x(i: number): number {
    const span = Math.max(1, props.data.length - 1);
    return (i / span) * W;
}

function y(v: number): number {
    return PAD_TOP + plotHeight.value * (1 - v / maxValue.value);
}

/** Contiguous runs of known values; each becomes its own line and fill. */
const segments = computed(() => {
    const runs: { i: number; v: number }[][] = [];
    let current: { i: number; v: number }[] = [];

    props.data.forEach((point, i) => {
        if (point.players === null) {
            if (current.length) runs.push(current);
            current = [];
            return;
        }
        current.push({ i, v: point.players });
    });
    if (current.length) runs.push(current);

    return runs.map(run => ({
        line: run.map((p, n) => `${n === 0 ? 'M' : 'L'}${x(p.i).toFixed(1)},${y(p.v).toFixed(1)}`).join(' '),
        // A single isolated point has no line to draw, so mark it with a dot.
        dot: run.length === 1 ? { cx: x(run[0].i), cy: y(run[0].v) } : null,
        fill: run.length > 1
            ? `M${x(run[0].i).toFixed(1)},${(PAD_TOP + plotHeight.value).toFixed(1)} `
                + run.map(p => `L${x(p.i).toFixed(1)},${y(p.v).toFixed(1)}`).join(' ')
                + ` L${x(run[run.length - 1].i).toFixed(1)},${(PAD_TOP + plotHeight.value).toFixed(1)} Z`
            : null,
    }));
});

const gridLines = computed(() => [0, 0.5, 1].map(f => ({
    y: PAD_TOP + plotHeight.value * f,
    value: Math.round(maxValue.value * (1 - f)),
})));

// ── Hover ────────────────────────────────────────────────────────
const hoverIndex = ref<number | null>(null);
const svgEl = ref<SVGSVGElement | null>(null);

const hovered = computed(() => {
    if (hoverIndex.value === null) return null;
    const point = props.data[hoverIndex.value];
    if (!point || point.players === null) return null;
    return { ...point, x: x(hoverIndex.value), y: y(point.players) };
});

function onMove(event: MouseEvent) {
    const rect = svgEl.value?.getBoundingClientRect();
    if (!rect || !rect.width) return;
    const ratio = (event.clientX - rect.left) / rect.width;
    hoverIndex.value = Math.max(0, Math.min(props.data.length - 1, Math.round(ratio * (props.data.length - 1))));
}

/** Over a day of data, the hour stops being the useful part of the label. */
const spansMultipleDays = computed(() => {
    if (props.data.length < 2) return false;
    const first = new Date(props.data[0].t).getTime();
    const last = new Date(props.data[props.data.length - 1].t).getTime();
    return last - first > 36 * 3600 * 1000;
});

function timeLabel(iso: string): string {
    const d = new Date(iso);
    return spansMultipleDays.value
        ? d.toLocaleDateString([], { month: 'short', day: 'numeric' })
        : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/** Full stamp for the tooltip, where the exact moment matters. */
function tooltipLabel(iso: string): string {
    const d = new Date(iso);
    return spansMultipleDays.value
        ? d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
        : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/** A handful of evenly spaced ticks — never one per point. */
const xTicks = computed(() => {
    if (props.data.length < 2) return [];
    const wanted = 6;
    const step = Math.max(1, Math.round((props.data.length - 1) / (wanted - 1)));
    const ticks: { left: number; label: string }[] = [];

    for (let i = 0; i < props.data.length; i += step) {
        ticks.push({ left: (i / (props.data.length - 1)) * 100, label: timeLabel(props.data[i].t) });
    }
    return ticks;
});
</script>

<template>
    <div v-if="!known.length" class="flex items-center justify-center text-[12.5px]"
        :style="{ height: H + 'px' }"
        :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
        {{ emptyLabel }}
    </div>

    <div v-else class="relative">
        <svg ref="svgEl" :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none"
            class="w-full block" :style="{ height: H + 'px' }"
            role="img" :aria-label="label"
            @mousemove="onMove" @mouseleave="hoverIndex = null">

            <defs>
                <linearGradient :id="`area-${label}`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.28" />
                    <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.02" />
                </linearGradient>
            </defs>

            <!-- Gridlines: hairline, solid, recessive -->
            <line v-for="g in gridLines" :key="g.y" x1="0" :y1="g.y" :x2="W" :y2="g.y"
                :stroke="dark ? '#27272a' : '#d3d7df'" stroke-width="1" vector-effect="non-scaling-stroke" />

            <g v-for="(seg, i) in segments" :key="i">
                <path v-if="seg.fill" :d="seg.fill" :fill="`url(#area-${label})`" />
                <path v-if="!seg.dot" :d="seg.line" fill="none" stroke="#3b82f6"
                    stroke-width="2" stroke-linejoin="round" stroke-linecap="round"
                    vector-effect="non-scaling-stroke" />
                <circle v-else :cx="seg.dot.cx" :cy="seg.dot.cy" r="3.5" fill="#3b82f6" />
            </g>

            <!-- Crosshair -->
            <g v-if="hovered">
                <line :x1="hovered.x" :y1="PAD_TOP" :x2="hovered.x" :y2="PAD_TOP + plotHeight"
                    :stroke="dark ? '#52525b' : '#a1a1aa'" stroke-width="1"
                    vector-effect="non-scaling-stroke" />
                <circle :cx="hovered.x" :cy="hovered.y" r="4" fill="#3b82f6"
                    :stroke="dark ? '#111113' : '#ffffff'" stroke-width="2"
                    vector-effect="non-scaling-stroke" />
            </g>
        </svg>

        <!-- Y labels sit outside the stretched SVG so they keep their aspect -->
        <div class="absolute inset-y-0 left-0 pointer-events-none flex flex-col justify-between"
            :style="{ paddingTop: PAD_TOP - 7 + 'px', paddingBottom: PAD_BOTTOM - 7 + 'px' }">
            <span v-for="g in gridLines" :key="g.value" class="text-[10px] tabular-nums leading-none px-1 rounded"
                :class="dark ? 'text-zinc-500 bg-[#111113]/80' : 'text-zinc-500 bg-white/80'">{{ g.value }}</span>
        </div>

        <div class="relative h-4 mt-1">
            <span v-for="(tick, i) in xTicks" :key="i"
                class="absolute text-[10px] whitespace-nowrap -translate-x-1/2 first:translate-x-0 last:-translate-x-full"
                :style="{ left: tick.left + '%' }"
                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ tick.label }}</span>
        </div>

        <!-- Tooltip -->
        <div v-if="hovered"
            class="absolute pointer-events-none px-2.5 py-1.5 rounded-lg border text-[11px] font-semibold whitespace-nowrap -translate-x-1/2 -translate-y-full"
            :style="{ left: (hovered.x / W * 100) + '%', top: (hovered.y - 8) + 'px' }"
            :class="dark ? 'border-zinc-700 bg-zinc-900 text-zinc-100' : 'border-zinc-300 bg-white text-zinc-900 shadow-md'">
            <span class="tabular-nums">{{ hovered.players }}</span>
            <span class="ml-1.5 font-normal" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                {{ tooltipLabel(hovered.t) }}
            </span>
        </div>
    </div>
</template>
