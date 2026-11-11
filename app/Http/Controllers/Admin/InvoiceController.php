<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $from = $request->date('from');
        $to = $request->date('to');

        $invoices = Invoice::with('user:id,username')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('id', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$search}%"))))
            ->when($from, fn ($q) => $q->whereDate('issued_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('issued_at', '<=', $to))
            ->latest('issued_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number(),
                'user' => $i->user?->username ?? 'Guest',
                'amount' => $i->amount / 100,
                'currency' => $i->currency,
                'issued_at' => $i->issued_at->format('Y-m-d'),
                'download_url' => route('invoices.download', ['invoice' => $i->id]),
            ]);

        // Sites here run one billing currency in practice — same simplification
        // the Shop admin dashboard already makes (config('app.currency') isn't
        // guaranteed set, so take the currency actually being used).
        $currency = Invoice::value('currency') ?? 'usd';
        $since = now()->startOfMonth();

        // One aggregate query instead of four separate COUNT/SUM round trips.
        $agg = DB::table('invoices')->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN currency = ? THEN amount ELSE 0 END) as total_amount,
             SUM(CASE WHEN issued_at >= ? THEN 1 ELSE 0 END) as month_count,
             SUM(CASE WHEN issued_at >= ? AND currency = ? THEN amount ELSE 0 END) as month_amount',
            [$currency, $since, $since, $currency],
        )->first();

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'q' => $search,
                'from' => $from?->toDateString() ?? '',
                'to' => $to?->toDateString() ?? '',
            ],
            'stats' => [
                'total' => (int) $agg->total,
                'totalAmount' => ((int) $agg->total_amount) / 100.0,
                'monthCount' => (int) $agg->month_count,
                'monthAmount' => ((int) $agg->month_amount) / 100.0,
                'currency' => $currency,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = $request->string('q')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        $rows = Invoice::with(['user:id,username,email', 'payment'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('id', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$search}%"))))
            ->when($from, fn ($q) => $q->whereDate('issued_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('issued_at', '<=', $to))
            ->orderBy('issued_at')
            ->lazy()
            ->map(fn (Invoice $i) => [
                $i->number(),
                $i->issued_at->toDateString(),
                $i->user?->username ?? 'Guest',
                $i->user?->email ?? '',
                number_format($i->amount / 100, 2, '.', ''),
                strtoupper($i->currency),
                $i->payment_id,
                $i->payment->gateway,
            ]);

        return Csv::download(
            'invoices-'.now()->format('Y-m-d').'.csv',
            ['number', 'issued_at', 'username', 'email', 'amount', 'currency', 'payment_id', 'gateway'],
            $rows,
        );
    }
}
