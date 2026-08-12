<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\PaymentReceiptNotification;
use App\Services\Extensions\Registries\ExtensionRegistry;

/**
 * Core's own registrations into the Extension SDK registries it defines —
 * same convention as CorePermissions/CoreNavigation/CoreWidgets, called from
 * ExtensionServiceProvider::boot() alongside them. Core is just another
 * registrant here, not a special case.
 */
class CorePayments
{
    public static function register(ExtensionRegistry $registry): void
    {
        $registry->payments()->on('paid', function (Payment $payment) {
            // Idempotent: ProcessPaymentEvent retries up to 3 times, and every
            // registered handler (this one included) runs again on retry.
            $invoice = Invoice::firstOrCreate(
                ['payment_id' => $payment->id],
                [
                    'user_id' => $payment->user_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'issued_at' => now(),
                ],
            );

            // Guests (nullable user_id, e.g. a guest Donation) have no account
            // to notify or list a receipt for — the Payment/Invoice rows still
            // exist and are visible to admins in the ledger.
            $payment->user?->notify(new PaymentReceiptNotification($payment, $invoice));
        });

        $registry->accountTabs()->register(
            key: 'transactions',
            label: 'account.tab_transactions',
            route: 'account.transactions.index',
            icon: 'Receipt',
            sort: 70,
        );

        $registry->navigation()->register(
            label: 'navigation.payments',
            route: 'admin.payments.index',
            icon: 'Receipt',
            section: 'navigation.sections.management',
            permission: 'payments.view',
            sort: 30,
        );
    }
}
