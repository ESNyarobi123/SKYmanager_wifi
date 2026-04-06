<?php

namespace App\Console\Commands;

use App\Jobs\AuthorizeHotspotPaymentJob;
use App\Models\HotspotPayment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reconcile-stuck-hotspot-payments')]
#[Description('Re-dispatch authorization jobs for hotspot payments that are paid but not yet authorized on the router.')]
class ReconcileStuckHotspotPayments extends Command
{
    public function handle(): int
    {
        $stuck = HotspotPayment::query()
            ->where('status', 'success')
            ->whereNotNull('transaction_id')
            ->orderBy('updated_at')
            ->limit(200)
            ->get();

        $count = 0;

        foreach ($stuck as $payment) {
            AuthorizeHotspotPaymentJob::dispatch($payment->id);
            $count++;
        }

        $this->info("Dispatched authorization jobs for {$count} payment(s).");

        return self::SUCCESS;
    }
}
