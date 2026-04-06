<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Models\User;
use App\Services\HotspotBundleService;
use App\Services\MikrotikApiService;
use Illuminate\Console\Command;

class ReconcileRouterBundleHealthCommand extends Command
{
    protected $signature = 'app:reconcile-router-bundle-health {router? : Router ULID} {--live : Verify files on router via API}';

    protected $description = 'Sync bundle metadata on VPS and optionally verify files on the router';

    public function handle(HotspotBundleService $bundles, MikrotikApiService $mikrotik): int
    {
        $id = $this->argument('router');
        $query = Router::query()->whereNotNull('user_id');
        if ($id) {
            $query->whereKey($id);
        }
        $routers = $query->get();
        if ($routers->isEmpty()) {
            $this->warn('No routers matched.');

            return self::FAILURE;
        }

        foreach ($routers as $router) {
            $customer = User::find($router->user_id);
            if (! $customer) {
                $this->warn("Skipping {$router->id}: no user_id.");

                continue;
            }
            $router->ensureLocalPortalToken();
            $meta = $bundles->syncBundleMetadata($router->fresh(), $customer);
            $this->line("{$router->id} bundle hash={$meta['bundle_hash']}");

            if ($this->option('live')) {
                try {
                    $v = $mikrotik->verifyHotspotBundle($router->fresh());
                    $this->line($v['ok'] ? '  live: OK' : '  live: FAIL — '.implode('; ', $v['issues']));
                } catch (\Throwable $e) {
                    $this->error('  live: '.$e->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }
}
