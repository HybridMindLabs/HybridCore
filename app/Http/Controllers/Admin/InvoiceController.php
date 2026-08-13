<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $invoices = Invoice::with('user:id,username')
            ->when($search !== '', fn ($q) => $q
                ->where('id', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$search}%")))
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
            'filters' => ['q' => $search],
            'stats' => [
                'total' => (int) $agg->total,
                'totalAmount' => ((int) $agg->total_amount) / 100.0,
                'monthCount' => (int) $agg->month_count,
                'monthAmount' => ((int) $agg->month_amount) / 100.0,
                'currency' => $currency,
            ],
        ]);
    }
}
