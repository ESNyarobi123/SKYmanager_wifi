<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerClientSessionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientSessionExportController extends Controller
{
    public function csv(Request $request, CustomerClientSessionService $sessions): StreamedResponse
    {
        $customer = Auth::user();

        $tab = in_array($request->query('tab'), ['active', 'history', 'all'], true)
            ? $request->query('tab')
            : 'all';

        $historyFrom = $this->parseDate($request->query('history_from'));
        $historyTo = $this->parseDate($request->query('history_to'));

        $collection = $sessions->sessionsForCustomer($customer, [
            'tab' => $tab,
            'router_id' => $request->query('router_id') ?: null,
            'search' => (string) $request->query('search', ''),
            'source_type' => (string) $request->query('source_type', 'all'),
            'plan_key' => $request->query('plan_key') ?: null,
            'access' => (string) $request->query('access', 'all'),
            'history_from' => $tab === 'history' ? $historyFrom : null,
            'history_to' => $tab === 'history' ? $historyTo : null,
        ]);

        $rows = $sessions->toCsvRows($collection);
        $filename = 'client-sessions-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, [
                'segment',
                'source',
                'client',
                'router',
                'ssid',
                'plan',
                'access_status',
                'wifi_note',
                'remaining',
                'started_at',
                'expires_at',
                'last_activity_at',
                'data_used_mb',
                'data_quota_mb',
                'reference',
                'router_live_state',
                'router_live_bytes_in',
                'router_live_bytes_out',
                'router_live_synced_at',
                'router_live_freshness',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
