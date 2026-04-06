<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\MikrotikApiService;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:reconcile-expired-access')]
#[Description('Expire subscriptions past end time, remove hotspot users from routers, and deactivate WiFi users when appropriate.')]
class ReconcileExpiredAccess extends Command
{
    public function __construct(private readonly MikrotikApiService $mikrotik)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with(['wifiUser', 'router'])
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired subscriptions found.');

            return self::SUCCESS;
        }

        $this->info("Processing {$expired->count()} expired subscription(s)...");

        foreach ($expired as $subscription) {
            if ($subscription->wifiUser && $subscription->router) {
                try {
                    $this->mikrotik
                        ->connect($subscription->router)
                        ->removeHotspotUser($subscription->wifiUser->mac_address);
                    $this->mikrotik->disconnect();
                } catch (Exception $e) {
                    Log::warning('ReconcileExpiredAccess: could not remove hotspot user', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $subscription->update(['status' => 'expired']);

            if ($subscription->wifiUser) {
                $hasActive = $subscription->wifiUser
                    ->subscriptions()
                    ->where('status', 'active')
                    ->exists();

                if (! $hasActive) {
                    $subscription->wifiUser->update(['is_active' => false]);
                }
            }

            $this->line(" - Expired subscription [{$subscription->id}]");
        }

        Log::info('ReconcileExpiredAccess: processed '.$expired->count().' expired subscriptions.');

        $this->info('Done.');

        return self::SUCCESS;
    }
}
