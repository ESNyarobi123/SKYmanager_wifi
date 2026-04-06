<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\Router;
use App\Models\RouterHotspotActiveSession;
use App\Services\AdminRouterRepairService;
use App\Services\RouterActiveSessionSyncService;
use App\Services\RouterHealthService;
use App\Support\AdminRouterSupportHints;
use App\Support\RouterOperationalReadiness;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RouterOperationsDetail extends Component
{
    use AuthorizesRequests;

    public Router $router;

    public string $flashMessage = '';

    public string $flashType = 'success';

    public string $verifyReportJson = '';

    public bool $showScriptModal = false;

    public string $generatedScript = '';

    public function mount(Router $router): void
    {
        $this->authorize('router-operations.view');
        $this->router = $router->load('user');
    }

    protected function ensureRepair(): void
    {
        $this->authorize('router-operations.repair');
    }

    public function refreshRouter(): void
    {
        $this->router->refresh();
        $this->router->load('user');
    }

    public function actionRecalculateHealth(bool $probe): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->recalculateHealth($this->router, $probe);
        $this->flash($result['ok'], $result['message']);
        $this->refreshRouter();
    }

    public function actionRegenerateScriptKeep(): void
    {
        $this->runRegenerateScript(false);
    }

    public function actionRegenerateScriptRotate(): void
    {
        $this->runRegenerateScript(true);
    }

    private function runRegenerateScript(bool $rotateCredentials): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->regenerateFullSetupScript($this->router, $rotateCredentials);
        if ($result['ok'] && isset($result['script'])) {
            $this->generatedScript = $result['script'];
            $this->showScriptModal = true;
        }
        $this->flash($result['ok'], $result['message']);
        $this->refreshRouter();
    }

    public function actionRegenerateBundle(): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->regenerateHotspotBundle($this->router);
        $this->flash($result['ok'], $result['message']);
        $this->refreshRouter();
    }

    public function actionVerifyOnboarding(bool $probe): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->verifyOnboarding($this->router, $probe);
        $this->verifyReportJson = json_encode($result['report'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->flash($result['ok'], $result['message']);
    }

    public function actionSyncTunnel(): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->syncTunnelState($this->router);
        $this->flash($result['ok'], $result['message']);
        $this->refreshRouter();
    }

    public function actionVerifyPortal(): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->verifyPortalBundleOnRouter($this->router);
        $this->flash($result['ok'], $result['message']);
        $this->refreshRouter();
    }

    public function actionRotateCredentials(): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->rotateApiCredentials($this->router);
        $this->flash($result['ok'], $result['message']);
        $this->refreshRouter();
    }

    public function actionMarkScriptReissued(): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->markScriptReissued($this->router);
        $this->flash($result['ok'], $result['message']);
        $this->refreshRouter();
    }

    public function actionMarkReOnboarding(): void
    {
        $this->ensureRepair();
        $result = app(AdminRouterRepairService::class)->markForReOnboarding($this->router);
        $this->flash($result['ok'], $result['message']);
        $this->refreshRouter();
    }

    public function actionSyncHotspotActiveSessions(): void
    {
        $this->ensureRepair();
        $result = app(RouterActiveSessionSyncService::class)->sync($this->router->fresh());
        if (! empty($result['skipped'])) {
            $this->flash(true, $result['message']);
        } else {
            $this->flash($result['ok'], $result['message']);
        }
        $this->refreshRouter();
        unset($this->storedHotspotActiveSessions);
    }

    /**
     * @return Collection<int, RouterHotspotActiveSession>
     */
    #[Computed]
    public function storedHotspotActiveSessions()
    {
        return RouterHotspotActiveSession::query()
            ->where('router_id', $this->router->id)
            ->orderByDesc('bytes_in')
            ->orderByDesc('bytes_out')
            ->get();
    }

    private function flash(bool $ok, string $message): void
    {
        $this->flashMessage = $message;
        $this->flashType = $ok ? 'success' : 'error';
    }

    public function readinessSnapshot(): array
    {
        return RouterOperationalReadiness::snapshot($this->router);
    }

    public function supportHintsList(): array
    {
        return AdminRouterSupportHints::forRouter($this->router);
    }

    public function healthEvaluateFresh(): array
    {
        return app(RouterHealthService::class)->evaluate($this->router, false);
    }

    public function recentActivityLogs()
    {
        return ActivityLog::query()
            ->where('subject_type', Router::class)
            ->where('subject_id', $this->router->id)
            ->latest()
            ->limit(40)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.router-operations-detail');
    }
}
