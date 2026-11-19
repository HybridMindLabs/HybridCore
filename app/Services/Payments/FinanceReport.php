<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Revenue figures for the admin finance page. Amounts are kept in minor
 * units (cents) like the payments table and are only ever summed within one
 * currency — mixing currencies would produce a meaningless total.
 *
 * Payments carry no separate paid-at time, so a payment counts in the period
 * it was created in; gateways settle within seconds, so the difference does
 * not move a payment between days in practice.
 */
class FinanceReport
{
    /** Subscription intervals expressed as a fraction of a month, for MRR. */
    private const PER_MONTH = ['day' => 365 / 12, 'week' => 52 / 12, 'month' => 1, 'year' => 1 / 12];

    /** @return array<string, mixed> */
    public function build(Carbon $from, Carbon $to, ?string $currency = null): array
    {
        $currencies = Payment::whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
            ->select('currency')->selectRaw('count(*) as n')->groupBy('currency')->orderByDesc('n')
            ->pluck('currency')->map(fn ($c) => strtoupper($c))->unique()->values();

        $currency = $currency !== null && $currencies->contains(strtoupper($currency))
            ? strtoupper($currency)
            : ($currencies->first() ?? 'EUR');

        $base = fn () => Payment::query()
            ->whereIn('currency', [$currency, strtolower($currency)])
            ->whereBetween('created_at', [$from, $to]);

        // Totals and breakdowns are summed by the database; only the per-day
        // series reads rows, and then just three narrow columns.
        $byStatus = $base()->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
            ->groupBy('status')->selectRaw('status, sum(amount) as total, count(*) as n')
            ->get()->keyBy('status');
        $net = (int) ($byStatus[Payment::STATUS_PAID]->total ?? 0);
        $orders = (int) ($byStatus[Payment::STATUS_PAID]->n ?? 0);
        $refunded = (int) ($byStatus[Payment::STATUS_REFUNDED]->total ?? 0);

        $paid = fn () => $base()->where('status', Payment::STATUS_PAID);

        $length = $from->diffInSeconds($to);
        $previousNet = (int) Payment::query()
            ->whereIn('currency', [$currency, strtolower($currency)])
            ->where('status', Payment::STATUS_PAID)
            ->whereBetween('created_at', [$from->copy()->subSeconds($length + 1), $from->copy()->subSecond()])
            ->sum('amount');

        return [
            'currency' => $currency,
            'currencies' => $currencies->all(),
            'totals' => [
                'net' => $net,
                'gross' => $net + $refunded,
                'refunded' => $refunded,
                'orders' => $orders,
                'average' => $orders ? (int) round($net / $orders) : 0,
                'customers' => (int) $paid()->whereNotNull('user_id')->distinct()->count('user_id'),
                'previous_net' => $previousNet,
            ],
            'series' => $this->series($base(), $from, $to),
            'by_gateway' => $this->rows($paid()->groupBy('gateway')->selectRaw('gateway as label, sum(amount) as revenue, count(*) as n')->get(),
                fn ($r) => Str::headline($r->label)),
            'by_source' => $this->rows($paid()->groupBy('payable_type')->selectRaw('payable_type as label, sum(amount) as revenue, count(*) as n')->get(),
                fn ($r) => Str::headline(class_basename($r->label))),
            'top_items' => $this->topItems($paid()),
            'top_customers' => $this->rows($paid()->whereNotNull('user_id')->groupBy('user_id')
                ->selectRaw('user_id as label, sum(amount) as revenue, count(*) as n')->orderByDesc('revenue')->limit(5)->get(),
                fn ($r) => User::whereKey($r->label)->value('username') ?? 'Deleted member'),
            'subscriptions' => $this->subscriptions($from, $to, $currency),
        ];
    }

    /**
     * Daily buckets for ranges up to two months, monthly beyond that.
     *
     * @return array<int, array{label: string, net: int, refunded: int}>
     */
    private function series(Builder $query, Carbon $from, Carbon $to): array
    {
        $daily = $from->diffInDays($to) <= 62;
        $key = $daily ? 'Y-m-d' : 'Y-m';

        $buckets = [];
        for ($cursor = $from->copy()->startOfDay(); $cursor <= $to; $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow()->startOfMonth()) {
            $buckets[$cursor->format($key)] = ['label' => $cursor->format($daily ? 'd M' : 'M Y'), 'net' => 0, 'refunded' => 0];
        }

        $rows = $query->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_REFUNDED])
            ->toBase()->get(['amount', 'status', 'created_at']);

        foreach ($rows as $row) {
            $bucket = Carbon::parse($row->created_at)->format($key);
            if (isset($buckets[$bucket])) {
                $buckets[$bucket][$row->status === Payment::STATUS_PAID ? 'net' : 'refunded'] += (int) $row->amount;
            }
        }

        return array_values($buckets);
    }

    /**
     * Revenue per purchased item. The ten best sellers are found in SQL; only
     * their payables are loaded, to read each item's display name.
     *
     * @return array<int, array{name: string, revenue: int, count: int}>
     */
    private function topItems(Builder $paid): array
    {
        $top = $paid->groupBy('payable_type', 'payable_id')
            ->selectRaw('payable_type, payable_id, sum(amount) as revenue, count(*) as n')
            ->orderByDesc('revenue')->limit(10)->get();

        return collect($top)
            ->map(function ($row) {
                $payment = (new Payment)->forceFill(['payable_type' => $row->payable_type, 'payable_id' => $row->payable_id]);

                return ['name' => $payment->description(), 'revenue' => (int) $row->revenue, 'count' => (int) $row->n];
            })
            // Two payables can share a display name (e.g. the same product re-created).
            ->groupBy('name')
            ->map(fn (Collection $g, string $name) => ['name' => $name, 'revenue' => $g->sum('revenue'), 'count' => $g->sum('count')])
            ->sortByDesc('revenue')->values()->all();
    }

    /**
     * @param  iterable<object>  $rows  each with label, revenue and n
     * @return array<int, array{name: string, revenue: int, count: int}>
     */
    private function rows(iterable $rows, callable $name): array
    {
        return collect($rows)
            ->map(fn ($r) => ['name' => $name($r), 'revenue' => (int) $r->revenue, 'count' => (int) $r->n])
            ->sortByDesc('revenue')->values()->all();
    }

    /** @return array<string, int> */
    private function subscriptions(Carbon $from, Carbon $to, string $currency): array
    {
        $inCurrency = fn ($q) => $q->whereIn('currency', [$currency, strtolower($currency)]);

        $active = Subscription::where('status', Subscription::STATUS_ACTIVE)->where($inCurrency)->get(['amount', 'interval']);

        return [
            'active' => $active->count(),
            'mrr' => (int) round($active->sum(fn (Subscription $s) => $s->amount * (self::PER_MONTH[$s->interval] ?? 1))),
            'past_due' => Subscription::where('status', Subscription::STATUS_PAST_DUE)->where($inCurrency)->count(),
            'started' => Subscription::where($inCurrency)->whereBetween('created_at', [$from, $to])->count(),
            'canceled' => Subscription::where('status', Subscription::STATUS_CANCELED)->where($inCurrency)->whereBetween('updated_at', [$from, $to])->count(),
        ];
    }
}
