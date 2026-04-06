<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\RouterHealthService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:router-health {router_id? : ULID of the router to check} {--probe : Live API + portal bundle verification on the router}')]
#[Description('Evaluate router health and persist onboarding_status + health_snapshot.')]
class RouterHealthCheck extends Command
{
    public function handle(RouterHealthService $health): int
    {
        $id = $this->argument('router_id');

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

        foreach ($routers as $router) {
            $report = $health->persist($router, $probe);
            $router->refresh();

            $this->line("{$router->name} ({$router->id})");
            $this->line('  overall: '.$report['overall']);
            $this->line('  tunnel:  '.$report['tunnel']['level'].' — '.$report['tunnel']['detail']);
            $this->line('  api:     '.$report['api']['level'].' — '.$report['api']['detail']);
            $this->line('  portal:  '.$report['portal']['level'].' — '.$report['portal']['detail']);
            $this->line('  → onboarding: '.$router->onboarding_status);

            if ($this->output->isVerbose()) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        return self::SUCCESS;
    }
}
