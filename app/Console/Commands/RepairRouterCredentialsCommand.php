<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\RouterCredentialSyncService;
use Illuminate\Console\Command;

class RepairRouterCredentialsCommand extends Command
{
    protected $signature = 'app:repair-router-credentials {router? : Router ULID}';

    protected $description = 'Rotate ZTP API password and bump credential version (customer must re-fetch setup script)';

    public function handle(RouterCredentialSyncService $credentials): int
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
            $pw = $credentials->rotateZtpPassword($router);
            $this->info("Rotated {$router->id} ({$router->name}) — new password stored in DB. Regenerate MikroTik script from dashboard.");
            if ($this->output->isVerbose()) {
                $this->line('  password: '.$pw);
            }
        }

        return self::SUCCESS;
    }
}
