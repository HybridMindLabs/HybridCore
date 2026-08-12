<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function download(Invoice $invoice): Response
    {
        abort_unless(
            $invoice->user_id === auth()->id() || (auth()->user()?->can('payments.view') ?? false),
            403,
        );

        $invoice->load(['payment.payable', 'user']);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'payment' => $invoice->payment,
            'siteName' => $this->settings->appName(),
        ]);

        return $pdf->download($invoice->number().'.pdf');
    }
}
