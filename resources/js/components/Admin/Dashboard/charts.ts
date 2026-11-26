import type Highcharts from 'highcharts';

export interface DayPoint { date: string; count: number }
export interface RoleSlice { name: string; color: string; count: number }
export interface GameBar { name: string; total: number }

export interface DashboardStats {
    total_users: number; new_users_today: number; new_users_week: number;
    banned_users: number; verified_users: number;
    servers_total: number | null; servers_online: number | null;
    total_messages: number | null;
    registrations_last_30_days: DayPoint[];
    logins_last_30_days: DayPoint[];
    role_distribution: RoleSlice[];
    top_games: GameBar[] | null;
    top_clicked_servers: { name: string; clicks: number }[];
    engagement: {
        reviews: number | null; reviews_week: number | null;
        comments: number | null; comments_week: number | null;
        follows: number | null; follows_week: number | null;
    };
}

export interface DashboardWidget { id: string; title: string; component: string; permission: string | null; props: Record<string, unknown> }

export interface Revenue {
    range: number; currency: string; today: number; month: number; period: number;
    paid_count: number; average: number; failed_count: number; refunded_count: number; refunded_amount: number;
    series: { date: string; amount: number }[];
}

export const palette = {
    blue: '#3b82f6',
    violet: '#8b5cf6',
    emerald: '#10b981',
    amber: '#f59e0b',
    rose: '#f43f5e',
    cyan: '#06b6d4',
};

/** "2026-11-26" → "26/11" */
export const shortDay = (date: string) => {
    const [, m, day] = date.split('-');
    return `${day}/${m}`;
};

/** Axis, grid, tooltip and legend colours shared by every chart, per theme. */
function base(dark: boolean) {
    const label = dark ? '#71717a' : '#9ca3af';
    const grid = dark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)';

    return {
        label,
        grid,
        chart: { backgroundColor: 'transparent', height: 260, style: { fontFamily: 'inherit' } },
        tooltip: {
            backgroundColor: dark ? '#18181b' : '#ffffff',
            borderColor: dark ? '#3f3f46' : '#e4e4e7',
            borderRadius: 10,
            borderWidth: 1,
            shadow: true,
            style: { color: dark ? '#f4f4f5' : '#18181b', fontSize: '12px', fontFamily: 'inherit' },
            padding: 10,
        },
        axisLabels: { style: { color: label, fontSize: '11px' } },
        legendItem: { color: label, fontWeight: '500', fontSize: '12px' },
        legendHover: { color: dark ? '#fff' : '#000' },
    };
}

const gradient = (rgb: string, top: number) => ({
    linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
    stops: [[0, `rgba(${rgb},${top})`], [1, `rgba(${rgb},0)`]] as [number, string][],
});

/** Registrations, and logins when they are tracked, one point per day. */
export function activityChart(stats: DashboardStats, dark: boolean): Highcharts.Options {
    const b = base(dark);
    const logins = stats.logins_last_30_days ?? [];

    return {
        chart: { ...b.chart, type: 'area', animation: { duration: 600 } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: { enabled: logins.length > 0, align: 'right', verticalAlign: 'top', itemStyle: b.legendItem, itemHoverStyle: b.legendHover },
        xAxis: {
            categories: stats.registrations_last_30_days.map((d) => shortDay(d.date)),
            tickInterval: 5, labels: b.axisLabels, lineColor: b.grid, tickColor: 'transparent',
        },
        yAxis: { min: 0, title: { text: undefined }, gridLineColor: b.grid, labels: b.axisLabels },
        tooltip: { ...b.tooltip, shared: true },
        plotOptions: {
            area: {
                marker: { enabled: false, states: { hover: { enabled: true, radius: 4 } } },
                lineWidth: 2,
                states: { hover: { lineWidth: 2 } },
            },
        },
        series: [
            { type: 'area', name: 'Registrations', data: stats.registrations_last_30_days.map((d) => d.count), color: palette.blue, fillColor: gradient('59,130,246', 0.25) },
            ...(logins.length ? [{ type: 'area' as const, name: 'Logins', data: logins.map((d) => d.count), color: palette.violet, fillColor: gradient('139,92,246', 0.18) }] : []),
        ],
    };
}

/** Users by primary role, with the total written into the donut's hole. */
export function rolesChart(roles: RoleSlice[], dark: boolean): Highcharts.Options {
    const b = base(dark);
    const total = roles.reduce((sum, r) => sum + r.count, 0);

    return {
        chart: {
            ...b.chart, type: 'pie', animation: { duration: 500 },
            events: {
                // Plain SVG text with text-anchor:middle: a useHTML label has no
                // size until after layout, so centring it with a CSS transform
                // rendered it off-centre, clipped by the ring.
                render(): void {
                    const chart = this as unknown as Highcharts.Chart & {
                        hcCenterValue?: Highcharts.SVGElement;
                        hcCenterCaption?: Highcharts.SVGElement;
                    };
                    const series = chart.series[0] as unknown as { center?: number[] } | undefined;
                    if (!series?.center) return;

                    const x = chart.plotLeft + series.center[0];
                    const y = chart.plotTop + series.center[1];

                    chart.hcCenterValue ??= chart.renderer.text('', 0, 0)
                        .css({ textAnchor: 'middle', pointerEvents: 'none', fontSize: '20px', fontWeight: '800' })
                        .add();
                    chart.hcCenterCaption ??= chart.renderer.text('', 0, 0)
                        .css({ textAnchor: 'middle', pointerEvents: 'none', fontSize: '10px', fontWeight: '600', letterSpacing: '.05em', textTransform: 'uppercase', fill: '#71717a' })
                        .add();

                    chart.hcCenterValue.attr({ x, y: y - 4, text: total.toLocaleString() }).css({ fill: dark ? '#f4f4f5' : '#18181b' });
                    chart.hcCenterCaption.attr({ x, y: y + 14, text: 'users' });
                },
            },
        },
        title: { text: undefined },
        credits: { enabled: false },
        tooltip: { ...b.tooltip, pointFormat: '<b>{point.y}</b> users ({point.percentage:.1f}%)' },
        legend: { enabled: true, layout: 'vertical', align: 'right', verticalAlign: 'middle', itemStyle: b.legendItem, itemHoverStyle: b.legendHover },
        plotOptions: {
            pie: {
                innerSize: '60%',
                dataLabels: {
                    enabled: true, distance: -28, format: '{point.percentage:.0f}%',
                    style: { color: '#fff', fontSize: '10px', fontWeight: '700', textOutline: 'none' },
                    filter: { property: 'percentage', operator: '>', value: 6 },
                },
                showInLegend: true,
                borderWidth: 0,
                states: { hover: { brightness: 0.08 } },
            },
        },
        series: [{ type: 'pie', name: 'Users', data: roles.map((r) => ({ name: r.name, y: r.count, color: r.color })) }],
    };
}

/** Servers per game. */
export function gamesChart(games: GameBar[], dark: boolean): Highcharts.Options {
    const b = base(dark);

    return {
        chart: { ...b.chart, type: 'bar', animation: { duration: 500 } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: { categories: games.map((g) => g.name), labels: b.axisLabels, lineColor: 'transparent', tickColor: 'transparent' },
        yAxis: { min: 0, title: { text: undefined }, gridLineColor: b.grid, labels: b.axisLabels },
        tooltip: { ...b.tooltip, pointFormat: '<b>{point.y}</b> servers' },
        plotOptions: {
            bar: {
                borderRadius: 4,
                dataLabels: { enabled: true, style: { color: b.label, fontSize: '11px', fontWeight: '500', textOutline: 'none' } },
                colorByPoint: true,
                colors: [palette.blue, palette.violet, palette.emerald, palette.amber, palette.rose, palette.cyan, '#a78bfa', '#fb923c'],
            },
        },
        series: [{ type: 'bar', name: 'Servers', data: games.map((g) => g.total) }],
    };
}

/** Every user as verified, unverified or banned. */
export function userStatusChart(stats: DashboardStats, dark: boolean): Highcharts.Options {
    const b = base(dark);
    const unverified = Math.max(0, stats.total_users - stats.verified_users - stats.banned_users);

    return {
        chart: { ...b.chart, type: 'column', animation: { duration: 500 } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: { enabled: true, align: 'center', verticalAlign: 'bottom', itemStyle: b.legendItem },
        xAxis: { categories: ['Users'], labels: { enabled: false }, lineColor: 'transparent', tickColor: 'transparent' },
        yAxis: { min: 0, title: { text: undefined }, gridLineColor: b.grid, labels: b.axisLabels },
        tooltip: { ...b.tooltip, shared: true },
        plotOptions: { column: { borderRadius: 6, borderWidth: 0, groupPadding: 0.1, pointPadding: 0.05 } },
        series: [
            { type: 'column', name: 'Verified', data: [stats.verified_users], color: palette.emerald },
            { type: 'column', name: 'Unverified', data: [unverified], color: palette.amber },
            { type: 'column', name: 'Banned', data: [stats.banned_users], color: palette.rose },
        ],
    };
}

/** Paid revenue per day. */
export function revenueChart(revenue: Revenue, dark: boolean, money: (amount: number) => string): Highcharts.Options {
    const b = base(dark);
    const series = revenue.series;

    return {
        chart: { ...b.chart, type: 'area', height: 220, animation: { duration: 500 } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: {
            categories: series.map((d) => shortDay(d.date)),
            tickInterval: series.length > 31 ? 14 : series.length > 7 ? 5 : 1,
            labels: b.axisLabels, lineColor: b.grid, tickColor: 'transparent',
        },
        yAxis: { min: 0, title: { text: undefined }, gridLineColor: b.grid, labels: b.axisLabels },
        tooltip: { ...b.tooltip, pointFormatter() { return `<b>${money((this as unknown as { y: number }).y)}</b>`; } },
        plotOptions: { area: { marker: { enabled: false, states: { hover: { enabled: true, radius: 4 } } }, lineWidth: 2 } },
        series: [{ type: 'area', name: 'Revenue', data: series.map((d) => d.amount), color: palette.emerald, fillColor: gradient('16,185,129', 0.25) }],
    };
}
