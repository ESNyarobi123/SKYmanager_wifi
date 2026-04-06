<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\RouterOnboardingService;
use Illuminate\Console\Command;

class MarkScriptAppliedCommand extends Command
{
    protected $signature = 'app:mark-script-applied {router : Router ULID}';

    protected $description = 'Set onboarding to script_pending (customer pasted script, awaiting verification)';

    public function handle(RouterOnboardingService $onboarding): int
    {
        $router = Router::findOrFail($this->argument('router'));
        $onboarding->markScriptAppliedPending($router);
        $this->info("Marked {$router->id} as script_pending.");

        return self::SUCCESS;
    }
}
