<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ActivityLogService;
use App\Services\Payments\PaymentService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ActivityLogService $activity,
    ) {}

    private const SORTS = ['created_at', 'amount', 'status'];

    /** @return array{status: string, search: string, from: string, to: string, sort: string, dir: string} */
    private function filters(Request $request): array
    {
        $sort = $request->string('sort')->toString();

        return [
            'status' => $request->string('status')->toString() ?: 'all',
            'search' => trim($request->string('search')->toString()),
            'from' => $request->date('from')?->toDateString() ?? '',
            'to' => $request->date('to')?->toDateString() ?? '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'created_at',
            'dir' => $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * @param  array{status: string, search: string, from: string, to: string, sort: string, dir: string}  $f
     * @return Builder<Payment>
     */
    private function query(array $f): Builder
    {
        $id = ltrim($f['search'], '#');
        $like = '%'.addcslashes($f['search'], '%_\\').'%';

        return Payment::query()
            ->when($f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->when($f['search'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('external_id', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('username', 'like', $like)->orWhere('email', 'like', $like))
                ->when(ctype_digit($id), fn ($w) => $w->orWhere('id', (int) $id))))
            ->when($f['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $f['from']))
            ->when($f['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $f['to']))
            ->orderBy($f['sort'], $f['dir'])
            ->orderBy('id', $f['dir']);
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $payments = $this->query($filters)
            ->with(['user', 'payable', 'invoice'])
            ->paginate($this->perPage($request, 25))
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'description' => $p->description(),
                'user' => $p->user ? ['username' => $p->user->username] : null,
                'amount' => $p->amount / 100,
                'currency' => $p->currency,
                'status' => $p->status,
                'gateway' => $p->gateway,
                'external_id' => $p->external_id,
                'created_at' => $p->created_at->diffForHumans(),
                'invoice_url' => $p->invoice ? route('invoices.download', ['invoice' => $p->invoice->id]) : null,
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'filters' => $filters,
            'totals' => [
                'paid' => Payment::where('status', Payment::STATUS_PAID)->count(),
                'refunded' => Payment::where('status', Payment::STATUS_REFUNDED)->count(),
                'failed' => Payment::where('status', Payment::STATUS_FAILED)->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($this->filters($request))
            ->with(['user', 'payable', 'invoice'])
            ->lazy()
            ->map(fn (Payment $p) => [
                $p->id,
                $p->created_at->toDateTimeString(),
                $p->user?->username ?? '',
                $p->user?->email ?? '',
                $p->description(),
                number_format($p->amount / 100, 2, '.', ''),
                strtoupper($p->currency),
                $p->status,
                $p->gateway,
                $p->external_id ?? '',
                $p->invoice?->number() ?? '',
            ]);

        return Csv::download(
            'payments-'.now()->format('Y-m-d').'.csv',
            ['id', 'created_at', 'username', 'email', 'description', 'amount', 'currency', 'status', 'gateway', 'external_id', 'invoice'],
            $rows,
        );
    }

    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === Payment::STATUS_PAID, 422, 'Only paid payments can be refunded.');

        $this->payments->refund($payment);
        $this->activity->log('payment.refunded', "Refunded payment #{$payment->id}");

        return back()->with('success', 'Payment refunded.');
    }
}
