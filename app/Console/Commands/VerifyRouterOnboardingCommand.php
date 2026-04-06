<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\RouterHealthService;
use Illuminate\Console\Command;

class VerifyRouterOnboardingCommand extends Command
{
    protected $signature = 'app:verify-router-onboarding {router? : Router ULID} {--probe : Live probe} {--json : Machine-readable output}';

    protected $description = 'Evaluate router onboarding dimensions without persisting (use --persist via app:router-health)';

    public function handle(RouterHealthService $health): int
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

        $probe = (bool) $this->option('probe');
        $out = [];

        foreach ($routers as $router) {
            $report = $health->evaluate($router, $probe);
            $out[$router->id] = $report;
            if (! $this->option('json')) {
                $this->line($router->id.' → '.$report['suggested_onboarding_status'].' ('.$report['overall'].')');
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
