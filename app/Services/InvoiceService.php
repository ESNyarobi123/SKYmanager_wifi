<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoiceService
{
    /**
     * Create an invoice record from a successful payment.
     */
    public function createFromPayment(Payment $payment): Invoice
    {
        $payment->load(['subscription.plan', 'subscription.router.customer']);

        $subscription = $payment->subscription;
        $customer = $subscription?->router?->customer;

        $subtotal = (float) $payment->amount;
        $taxRate = (float) config('invoice.tax_rate', 0);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = $subtotal + $taxAmount;

        return Invoice::create([
            'invoice_number' => Invoice::generateNumber(),
            'customer_id' => $customer?->id,
            'payment_id' => $payment->id,
            'subscription_id' => $subscription?->id,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'currency' => 'TZS',
            'status' => 'paid',
            'issued_at' => now(),
            'due_at' => now(),
        ]);
    }

    /**
     * Generate and stream a branded PDF for the given invoice.
     */
    public function downloadPdf(Invoice $invoice): Response
    {
        $invoice->load(['customer', 'subscription.plan', 'subscription.router', 'payment']);

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'))
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false);

        $filename = $invoice->invoice_number.'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
