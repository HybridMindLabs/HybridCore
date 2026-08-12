<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ActivityLogService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ActivityLogService $activity,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'all';

        $payments = Payment::with(['user', 'payable'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
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
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'filters' => ['status' => $status],
            'totals' => [
                'paid' => Payment::where('status', Payment::STATUS_PAID)->count(),
                'refunded' => Payment::where('status', Payment::STATUS_REFUNDED)->count(),
                'failed' => Payment::where('status', Payment::STATUS_FAILED)->count(),
            ],
        ]);
    }

    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === Payment::STATUS_PAID, 422, 'Only paid payments can be refunded.');

        $this->payments->refund($payment);
        $this->activity->log('payment.refunded', "Refunded payment #{$payment->id}");

        return back()->with('success', 'Payment refunded.');
    }
}
