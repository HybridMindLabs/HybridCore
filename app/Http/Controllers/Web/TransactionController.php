<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->with('invoice')
            ->latest('created_at')
            ->paginate(20)
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'description' => $p->description(),
                'amount' => $p->amount / 100,
                'currency' => $p->currency,
                'status' => $p->status,
                'gateway' => $p->gateway,
                'created_at' => $p->created_at,
                'invoice_url' => $p->invoice
                    ? route('invoices.download', ['invoice' => $p->invoice->id])
                    : null,
                // Shop is optional — only link to its itemized order view
                // when the extension is actually installed.
                'items_url' => $p->payable_type === 'Hybridcore\\Shop\\Models\\ShopOrder' && Route::has('shop.orders.show')
                    ? route('shop.orders.show', $p->payable_id)
                    : null,
            ]);

        $subscriptions = Subscription::where('user_id', $user->id)
            ->latest('created_at')
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'description' => $s->description(),
                'amount' => $s->amount / 100,
                'currency' => $s->currency,
                'interval' => $s->interval,
                'status' => $s->status,
                'current_period_end' => $s->current_period_end,
                'cancel_at_period_end' => $s->cancel_at_period_end,
            ]);

        $totalSpent = Payment::where('user_id', $user->id)->where('status', Payment::STATUS_PAID)->sum('amount');

        return Inertia::render('Account/Transactions', [
            'payments' => $payments,
            'subscriptions' => $subscriptions,
            'totalSpent' => $totalSpent / 100,
            'totalSpentCurrency' => Payment::where('user_id', $user->id)->value('currency') ?? 'usd',
            'unreadNotifications' => $user->unreadNotifications()->count(),
            'unreadMessages' => $user->unreadMessagesCount(),
        ]);
    }

    public function cancelSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 403);

        $this->payments->cancelSubscription($subscription);

        return back()->with('success', trans('account.tx_canceled_message'));
    }
}
