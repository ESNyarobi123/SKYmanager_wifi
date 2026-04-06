<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\ReportCsvExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(public InvoiceService $invoiceService) {}

    public function download(Invoice $invoice): Response
    {
        abort_unless($invoice->customer_id === Auth::id(), 403);

        return $this->invoiceService->downloadPdf($invoice);
    }

    public function exportCsv(Request $request, ReportCsvExportService $exports): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->subYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();

        return $exports->stream(Auth::user(), 'invoices', $from, $to);
    }
}
