<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\MikrotikApiService;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:expire-sessions')]
#[Description('Expire active subscriptions past their end time and remove hotspot users from routers.')]
class ExpireSessions extends Command
{
    public function __construct(private readonly MikrotikApiService $mikrotik)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with(['wifiUser', 'router'])
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired sessions found.');

            return self::SUCCESS;
        }

        $this->info("Processing {$expired->count()} expired subscription(s)...");

        foreach ($expired as $subscription) {
            try {
                $this->mikrotik
                    ->connect($subscription->router)
                    ->removeHotspotUser($subscription->wifiUser->mac_address);

                $this->mikrotik->disconnect();
            } catch (Exception $e) {
                Log::warning('ExpireSessions: could not remove hotspot user', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $subscription->update(['status' => 'expired']);
            $subscription->wifiUser->update(['is_active' => false]);

            $this->line(" - Expired subscription [{$subscription->id}] for user [{$subscription->wifiUser->mac_address}]");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
