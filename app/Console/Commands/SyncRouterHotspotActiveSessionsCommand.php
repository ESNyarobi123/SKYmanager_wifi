<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\RouterActiveSessionSyncService;
use App\Support\RouterOnboarding;
use Illuminate\Console\Command;
use Throwable;

class SyncRouterHotspotActiveSessionsCommand extends Command
{
    protected $signature = 'skymanager:sync-router-hotspot-sessions
                            {router? : Router ULID (omit to batch)}
                            {--all-ready : Only routers with onboarding status ready and a customer}';

    protected $description = 'Poll RouterOS /ip/hotspot/active and refresh stored snapshots (and matched hotspot payment usage).';

    public function handle(RouterActiveSessionSyncService $sync): int
    {
        $id = $this->argument('router');

        if ($id) {
            $router = Router::query()->find($id);
            if (! $router) {
                $this->error('Router not found.');

                return self::FAILURE;
            }

            return $this->runOne($sync, $router);
        }

        $q = Router::query()->whereNotNull('user_id')->whereNotNull('ip_address');

        if ($this->option('all-ready')) {
            $q->where('onboarding_status', RouterOnboarding::READY);
        }

        $routers = $q->orderBy('id')->get();
        if ($routers->isEmpty()) {
            $this->warn('No routers matched.');

            return self::SUCCESS;
        }

        $sleepMs = max(0, (int) config('skymanager.router_hotspot_sessions_sync_sleep_ms', 250));
        $ok = 0;
        $fail = 0;

        foreach ($routers as $router) {
            try {
                $result = $sync->sync($router);
                if (! empty($result['skipped'])) {
                    $this->line("[skip] {$router->name}: {$result['message']}");
                } elseif ($result['ok']) {
                    $this->info("[ok] {$router->name}: {$result['message']}");
                    $ok++;
                } else {
                    $this->warn("[fail] {$router->name}: {$result['message']}");
                    $fail++;
                }
            } catch (Throwable $e) {
                $this->error("[error] {$router->name}: {$e->getMessage()}");
                $fail++;
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->line("Done. Success: {$ok}, failed: {$fail}.");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runOne(RouterActiveSessionSyncService $sync, Router $router): int
    {
        $result = $sync->sync($router);
        if (! empty($result['skipped'])) {
            $this->line($result['message']);

            return self::SUCCESS;
        }

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
