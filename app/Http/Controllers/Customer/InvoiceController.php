<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function __construct(public InvoiceService $invoiceService) {}

    public function download(Invoice $invoice): Response
    {
        abort_unless($invoice->customer_id === Auth::id(), 403);

        return $this->invoiceService->downloadPdf($invoice);
    }
}
