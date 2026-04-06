<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams CSV exports — paired with OperationsReportService and tenant scope.
 */
class ReportCsvExportService
{
    private const TYPES = [
        'revenue',
        'hotspot_payments',
        'router_operations',
        'plan_performance',
        'support_incidents',
        'invoices',
        'vouchers',
    ];

    public function __construct(
        private readonly OperationsReportService $reports,
    ) {}

    public static function allowedTypes(): array
    {
        return self::TYPES;
    }

    public function stream(User $actor, string $type, CarbonInterface $from, CarbonInterface $to): StreamedResponse
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Invalid export type.');
        }

        if ($type === 'support_incidents') {
            $summary = $this->reports->supportIncidentsReport($actor);
            $headers = array_keys($summary);
            $rows = collect([$summary]);
            $filename = 'support-incidents-summary-'.now()->toDateString().'.csv';
        } elseif ($type === 'revenue') {
            $rows = $this->reports->revenueReport($actor, $from, $to);
            $headers = ['id', 'created_at', 'status', 'amount', 'provider', 'reference', 'customer_phone', 'plan_name', 'router_id', 'router_name'];
            $filename = 'revenue-report-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';
        } elseif ($type === 'hotspot_payments') {
            $rows = $this->reports->hotspotPaymentReport($actor, $from, $to);
            $headers = ['id', 'reference', 'created_at', 'status', 'amount', 'phone', 'router_id', 'router_name', 'plan_name', 'provider_confirmed_at', 'authorized_at', 'authorize_attempts', 'retry_exhausted_at', 'recovered_after_failure_at', 'last_authorize_error'];
            $filename = 'hotspot-payments-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';
        } elseif ($type === 'router_operations') {
            $rows = $this->reports->routerOperationsReport($actor);
            $headers = ['id', 'name', 'owner_id', 'owner_name', 'is_online', 'onboarding_status', 'credential_mismatch', 'bundle_mode', 'portal_bundle_version', 'health_overall', 'updated_at'];
            $filename = 'router-operations-snapshot-'.now()->toDateString().'.csv';
        } elseif ($type === 'plan_performance') {
            $rows = $this->reports->planPerformanceReport($actor, $from, $to);
            $headers = ['plan_id', 'plan_name', 'purchase_count', 'authorized_count', 'failed_count', 'stuck_auth_count', 'authorize_success_rate_pct'];
            $filename = 'plan-performance-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';
        } elseif ($type === 'invoices') {
            $rows = $this->reports->invoiceReport($actor, $from, $to);
            $headers = ['invoice_number', 'status', 'total', 'currency', 'issued_at', 'due_at', 'customer_id', 'customer_name', 'plan_name', 'router_name'];
            $filename = 'invoices-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';
        } else {
            $rows = $this->reports->voucherReport($actor, $from, $to);
            $headers = ['code', 'batch_name', 'status', 'plan_name', 'used_at', 'expires_at', 'created_at', 'customer_id'];
            $filename = 'customer-vouchers-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';
        }

        ActivityLog::record(
            'Report CSV exported',
            null,
            $actor,
            ['export_type' => $type, 'from' => $from->toDateString(), 'to' => $to->toDateString()]
        );

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $v = is_array($row) ? ($row[$h] ?? '') : (is_object($row) ? ($row->{$h} ?? '') : '');
                    if (is_bool($v)) {
                        $v = $v ? '1' : '0';
                    }
                    $line[] = $v;
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
