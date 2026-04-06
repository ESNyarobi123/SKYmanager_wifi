<?php

namespace App\Services;

use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\RouterHotspotActiveSession;
use App\Support\RouterMacAddress;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Polls RouterOS `/ip/hotspot/active/print`, replaces the per-router snapshot, and optionally
 * updates hotspot_payments when a single authorized row matches by MAC.
 */
class RouterActiveSessionSyncService
{
    public function __construct(private MikrotikApiService $mikrotik) {}

    /**
     * @return array{ok: bool, message: string, sessions: int, skipped?: bool}
     */
    public function sync(Router $router): array
    {
        if (! $router->ip_address) {
            return [
                'ok' => true,
                'message' => 'Router has no IP address; skipped.',
                'sessions' => 0,
                'skipped' => true,
            ];
        }

        try {
            $this->mikrotik->connect($router);
            $rows = $this->mikrotik->getActiveHotspotSessions();
            $this->mikrotik->disconnect();
        } catch (Throwable $e) {
            try {
                $this->mikrotik->disconnect();
            } catch (Throwable) {
                // ignore secondary disconnect errors
            }

            $router->forceFill([
                'hotspot_sessions_sync_error' => Str::limit($e->getMessage(), 2000),
            ])->save();

            return [
                'ok' => false,
                'message' => 'Hotspot session sync failed: '.$e->getMessage(),
                'sessions' => 0,
            ];
        }

        $syncedAt = now();
        $parsed = $this->aggregateByMac($this->parseMikrotikRows($rows, $router->id, $syncedAt));

        DB::transaction(function () use ($router, $parsed, $syncedAt) {
            RouterHotspotActiveSession::query()->where('router_id', $router->id)->delete();

            foreach (array_chunk($parsed, 100) as $chunk) {
                RouterHotspotActiveSession::query()->insert($chunk);
            }

            $router->forceFill([
                'hotspot_sessions_synced_at' => $syncedAt,
                'hotspot_sessions_sync_error' => null,
            ])->save();

            $this->matchHotspotPaymentUsage($router, $syncedAt, $parsed);
        });

        return [
            'ok' => true,
            'message' => 'Synced '.count($parsed).' active hotspot session(s).',
            'sessions' => count($parsed),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $parsed
     */
    private function matchHotspotPaymentUsage(Router $router, CarbonInterface $syncedAt, array $parsed): void
    {
        foreach ($parsed as $row) {
            $mac = $row['mac_address'] ?? null;
            if (! is_string($mac) || $mac === '') {
                continue;
            }

            $candidates = HotspotPayment::query()
                ->where('router_id', $router->id)
                ->where('status', 'authorized')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->get()
                ->filter(fn (HotspotPayment $p) => RouterMacAddress::matches($p->client_mac, $mac));

            if ($candidates->count() !== 1) {
                continue;
            }

            /** @var HotspotPayment $payment */
            $payment = $candidates->first();
            $payment->forceFill([
                'router_bytes_in' => (int) ($row['bytes_in'] ?? 0),
                'router_bytes_out' => (int) ($row['bytes_out'] ?? 0),
                'router_usage_synced_at' => $syncedAt,
            ])->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function aggregateByMac(array $rows): array
    {
        $byMac = [];

        foreach ($rows as $row) {
            $mac = $row['mac_address'] ?? '';
            if ($mac === '') {
                continue;
            }

            if (! isset($byMac[$mac])) {
                $byMac[$mac] = $row;

                continue;
            }

            $byMac[$mac]['bytes_in'] = (int) $byMac[$mac]['bytes_in'] + (int) ($row['bytes_in'] ?? 0);
            $byMac[$mac]['bytes_out'] = (int) $byMac[$mac]['bytes_out'] + (int) ($row['bytes_out'] ?? 0);
            if (($row['uptime_seconds'] ?? null) !== null) {
                $byMac[$mac]['uptime_seconds'] = max((int) ($byMac[$mac]['uptime_seconds'] ?? 0), (int) $row['uptime_seconds']);
            }
        }

        return array_values($byMac);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function parseMikrotikRows(array $rows, string $routerId, CarbonInterface $syncedAt): array
    {
        $out = [];
        $now = now();

        foreach ($rows as $row) {
            if (isset($row['message'])) {
                continue;
            }

            $id = $row['.id'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }

            $macRaw = $row['mac-address'] ?? $row['mac_address'] ?? '';
            $mac = RouterMacAddress::normalize(is_string($macRaw) ? $macRaw : null);
            if ($mac === null) {
                continue;
            }

            $bytesIn = $this->parseUint($row['bytes-in'] ?? $row['bytes_in'] ?? '0');
            $bytesOut = $this->parseUint($row['bytes-out'] ?? $row['bytes_out'] ?? '0');
            $uptimeRaw = $row['uptime'] ?? null;
            $uptimeRawStr = is_string($uptimeRaw) ? $uptimeRaw : (is_numeric($uptimeRaw) ? (string) $uptimeRaw : null);
            $uptimeSeconds = $this->parseUptimeSeconds($uptimeRawStr);

            $out[] = [
                'router_id' => $routerId,
                'mikrotik_internal_id' => $id,
                'mac_address' => $mac,
                'ip_address' => $this->stringOrNull($row['address'] ?? $row['ip'] ?? null),
                'user_name' => $this->stringOrNull($row['user'] ?? null),
                'bytes_in' => $bytesIn,
                'bytes_out' => $bytesOut,
                'uptime_seconds' => $uptimeSeconds,
                'uptime_raw' => $uptimeRawStr ? Str::limit($uptimeRawStr, 64) : null,
                'synced_at' => $syncedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $out;
    }

    private function parseUint(mixed $v): int
    {
        if (is_int($v)) {
            return max(0, $v);
        }

        if (is_string($v) && ctype_digit($v)) {
            return (int) $v;
        }

        return 0;
    }

    private function stringOrNull(mixed $v): ?string
    {
        if (! is_string($v) || $v === '') {
            return null;
        }

        return Str::limit($v, 191);
    }

    private function parseUptimeSeconds(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        return null;
    }
}
