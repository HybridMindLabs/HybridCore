<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payments\FinanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FinanceReportController extends Controller
{
    private const RANGES = ['30d', '90d', '12m', 'ytd', 'custom'];

    public function index(Request $request, FinanceReport $report): Response
    {
        $range = in_array($request->query('range'), self::RANGES, true) ? $request->query('range') : '30d';
        [$from, $to] = $this->period($range, $request);

        return Inertia::render('Admin/Finance/Index', [
            'report' => $report->build($from, $to, $request->query('currency')),
            'filters' => [
                'range' => $range,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(string $range, Request $request): array
    {
        $to = now()->endOfDay();

        if ($range === 'custom') {
            $from = $request->date('from')?->startOfDay() ?? now()->subDays(29)->startOfDay();
            $to = $request->date('to')?->endOfDay() ?? $to;

            // A reversed range is a typo, not a request for nothing.
            return $from->greaterThan($to) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
        }

        return [match ($range) {
            '90d' => now()->subDays(89)->startOfDay(),
            '12m' => now()->subMonthsNoOverflow(11)->startOfMonth(),
            'ytd' => now()->startOfYear(),
            default => now()->subDays(29)->startOfDay(),
        }, $to];
    }
}
