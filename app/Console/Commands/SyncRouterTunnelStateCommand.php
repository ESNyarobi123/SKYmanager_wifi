<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\MikrotikApiService;
use Illuminate\Console\Command;

class SyncRouterTunnelStateCommand extends Command
{
    protected $signature = 'app:sync-router-tunnel-state {router? : Router ULID}';

    protected $description = 'Update wg_last_handshake_at and tunnel flags via live API';

    public function handle(MikrotikApiService $mikrotik): int
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
            try {
                $mikrotik->connectZtp($router);
                try {
                    $probe = $mikrotik->probeTunnelUpWhileConnected($router);
                    $up = $probe['tunnel_up'];
                    $handshake = $probe['handshake_at'];
                } finally {
                    $mikrotik->disconnect();
                }

                $router->forceFill([
                    'last_tunnel_check_at' => now(),
                    'last_tunnel_ok' => $up,
                    'vpn_connected' => $up,
                    'wg_last_handshake_at' => $handshake,
                ])->save();

                $this->info("[OK] {$router->id} tunnel=".($up ? 'up' : 'down'));
            } catch (\Throwable $e) {
                $this->error("[FAIL] {$router->id}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
