<?php

namespace App\Console\Commands;

use App\Models\RouterHotspotActiveSession;
use Illuminate\Console\Command;

class PruneRouterHotspotActiveSessionsCommand extends Command
{
    protected $signature = 'skymanager:prune-router-hotspot-sessions {--days=14 : Delete snapshot rows older than this many days}';

    protected $description = 'Remove hotspot active-session snapshot rows older than the given age (orphan / stale cache cleanup).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = RouterHotspotActiveSession::query()->where('synced_at', '<', $cutoff)->delete();
        $this->info("Deleted {$deleted} row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
