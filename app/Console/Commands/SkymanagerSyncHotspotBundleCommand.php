<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Models\User;
use App\Services\HotspotBundleService;
use Illuminate\Console\Command;

class SkymanagerSyncHotspotBundleCommand extends Command
{
    protected $signature = 'skymanager:sync-hotspot-bundle {router? : Router ULID (omit to sync all claimed routers)}';

    protected $description = 'Recompute hotspot bundle metadata (hash, folder, version) for MikroTik fetch URLs';

    public function handle(HotspotBundleService $bundles): int
    {
        $id = $this->argument('router');
        $query = Router::query()->whereNotNull('user_id');

        if ($id !== null && $id !== '') {
            $query->whereKey($id);
        }

        $count = 0;

        $query->orderBy('id')->each(function (Router $router) use ($bundles, &$count) {
            $user = $router->user ?? new User;
            $router->ensureLocalPortalToken();
            $bundles->syncBundleMetadata($router->fresh(), $user);
            $count++;
            $this->line("Synced {$router->id} — {$router->name}");
        });

        if ($count === 0) {
            $this->warn('No routers matched.');

            return self::FAILURE;
        }

        $this->info("Done. {$count} router(s).");

        return self::SUCCESS;
    }
}
