<?php

namespace App\Http\Controllers;

use App\Services\ReportCsvExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function download(Request $request, string $type, ReportCsvExportService $exports): StreamedResponse
    {
        if (! in_array($type, ReportCsvExportService::allowedTypes(), true)) {
            abort(404);
        }

        $from = Carbon::parse($request->query('from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();

        return $exports->stream(Auth::user(), $type, $from, $to);
    }
}
