<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\MikrotikApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(MikrotikApiService $mikrotik): void
    {
        $expired = Subscription::where('status', 'active')
            ->where('expires_at', '<', now())
            ->with(['wifiUser', 'router'])
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            if ($subscription->wifiUser && $subscription->router) {
                try {
                    $mikrotik->connect($subscription->router);
                    $mikrotik->removeHotspotUser($subscription->wifiUser->mac_address);
                    $mikrotik->disconnect();
                } catch (\Exception $e) {
                    Log::warning('ExpireSubscriptions: could not remove user from router', [
                        'mac' => $subscription->wifiUser->mac_address,
                        'router' => $subscription->router->name,
                        'error' => $e->getMessage(),
                    ]);
                }

                $hasActive = $subscription->wifiUser
                    ->subscriptions()
                    ->where('status', 'active')
                    ->exists();

                if (! $hasActive) {
                    $subscription->wifiUser->update(['is_active' => false]);
                }
            }
        }

        Log::info('ExpireSubscriptions: processed '.$expired->count().' expired subscriptions.');
    }
}
