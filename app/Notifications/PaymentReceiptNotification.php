<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
        private readonly ?Invoice $invoice,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'payment.received',
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->amount / 100,
            'currency' => $this->payment->currency,
            'message' => trans('account.payment_received', [
                'amount' => number_format($this->payment->amount / 100, 2),
                'currency' => $this->payment->currency,
                'description' => $this->payment->description(),
            ]),
            'action_url' => route('account.transactions.index'),
            'action_label' => trans('account.tab_transactions'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(trans('account.payment_receipt_subject', ['description' => $this->payment->description()]))
            ->greeting(trans('account.payment_receipt_greeting'))
            ->line(trans('account.payment_received', [
                'amount' => number_format($this->payment->amount / 100, 2),
                'currency' => $this->payment->currency,
                'description' => $this->payment->description(),
            ]));

        if ($this->invoice !== null) {
            $mail->action(trans('account.payment_receipt_download'), route('invoices.download', $this->invoice));
        }

        return $mail->line(trans('account.payment_receipt_outro'));
    }
}
