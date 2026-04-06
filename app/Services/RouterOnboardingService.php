<?php

namespace App\Services;

use App\Models\Router;
use App\Support\RouterOnboarding;

class RouterOnboardingService
{
    public function recordClaim(Router $router): void
    {
        $router->forceFill([
            'onboarding_status' => RouterOnboarding::CLAIMED,
            'claimed_at' => $router->claimed_at ?? now(),
            'ready_at' => null,
        ])->save();
    }

    /**
     * @param  list<string|array{code: string, message: string}>  $warnings
     */
    public function recordScriptGenerated(Router $router, array $warnings = [], ?string $errorCode = null): void
    {
        $existing = $router->onboarding_warnings ?? [];
        $existing['script_lines'] = $warnings;

        $attrs = [
            'script_generated_at' => now(),
            'onboarding_warnings' => $existing,
            'onboarding_status' => $errorCode !== null ? RouterOnboarding::ERROR : RouterOnboarding::SCRIPT_GENERATED,
        ];

        if ($errorCode !== null) {
            $attrs['last_error_code'] = $errorCode;
            $attrs['last_error_message'] = implode("\n", array_filter(array_map(
                fn ($w) => is_string($w) ? $w : ($w['message'] ?? json_encode($w)),
                $warnings
            )));
        } else {
            $attrs['last_error_code'] = null;
            $attrs['last_error_message'] = null;
        }

        $router->forceFill($attrs)->save();
    }

    public function recordScriptDownloaded(Router $router): void
    {
        $attrs = [
            'script_downloaded_at' => now(),
        ];
        if ($router->onboarding_status !== RouterOnboarding::ERROR) {
            $attrs['onboarding_status'] = RouterOnboarding::SCRIPT_DOWNLOADED;
        }
        $router->forceFill($attrs)->save();
    }

    public function markScriptAppliedPending(Router $router): void
    {
        $router->forceFill([
            'script_applied_at' => now(),
            'onboarding_status' => RouterOnboarding::SCRIPT_PENDING,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function applyHealthEvaluation(Router $router, array $snapshot, string $suggestedStatus): void
    {
        $router->forceFill([
            'health_snapshot' => $snapshot,
            'health_evaluated_at' => now(),
            'onboarding_status' => $suggestedStatus,
        ]);

        if ($suggestedStatus === RouterOnboarding::READY) {
            $router->ready_at = $router->ready_at ?? now();
        }

        $router->save();
    }

    public function clearCredentialMismatch(Router $router): void
    {
        $router->forceFill(['credential_mismatch_suspected' => false])->save();
    }
}
