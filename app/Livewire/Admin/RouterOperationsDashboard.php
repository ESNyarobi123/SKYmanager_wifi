<?php

namespace App\Livewire\Admin;

use App\Models\Router;
use App\Services\PaymentIncidentSummaryService;
use App\Support\RouterOnboarding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class RouterOperationsDashboard extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    /** @var 'all'|'healthy'|'warning'|'error'|'unknown'|'claimed'|'tunnel_pending'|'api_failed'|'bundle_mismatch'|'cred_mismatch'|'offline'|'legacy_mode'|'ready' */
    public string $filter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('router-operations.view');
    }

    #[Computed]
    public function incidentSummary(): array
    {
        $s = app(PaymentIncidentSummaryService::class)->summarize();

        return [
            'long_claimed' => $s['routers_long_claimed'],
            'tunnel_stuck' => $s['routers_tunnel_stuck'],
            'cred_flags' => $s['routers_cred_flags'],
            'bundle_mismatch' => $s['routers_bundle_mismatch'],
            'routers_offline' => $s['routers_offline_status'],
            'hotspot_stuck' => $s['hotspot_stuck_authorizing'],
            'hotspot_retry_exhausted' => $s['hotspot_retry_exhausted'],
            'hotspot_provider_pending_auth' => $s['hotspot_provider_confirmed_not_authorized'],
            'hotspot_failures_24h' => $s['hotspot_authorize_failures_24h'],
        ];
    }

    public function paginatedRouters()
    {
        $q = Router::query()
            ->with('user')
            ->orderByDesc('updated_at');

        if ($this->search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', $term)
                    ->orWhere('id', 'like', $term)
                    ->orWhere('mac_address', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhereHas('user', function ($uq) use ($term) {
                        $uq->where('name', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        }

        match ($this->filter) {
            'healthy' => $q->where('health_snapshot->overall', 'healthy'),
            'warning' => $q->where('health_snapshot->overall', 'warning'),
            'error' => $q->where('health_snapshot->overall', 'error'),
            'unknown' => $q->where(function ($qq) {
                $qq->whereNull('health_snapshot')
                    ->orWhere('health_snapshot->overall', 'unknown');
            }),
            'claimed' => $q->where('onboarding_status', RouterOnboarding::CLAIMED),
            'tunnel_pending' => $q->where('onboarding_status', RouterOnboarding::TUNNEL_PENDING),
            'api_failed' => $q->where(function ($qq) {
                $qq->where('onboarding_status', RouterOnboarding::API_PENDING)
                    ->orWhere('onboarding_status', RouterOnboarding::OFFLINE);
            }),
            'bundle_mismatch' => $q->where('onboarding_status', RouterOnboarding::BUNDLE_MISMATCH),
            'cred_mismatch' => $q->where(function ($qq) {
                $qq->where('onboarding_status', RouterOnboarding::CRED_MISMATCH)
                    ->orWhere('credential_mismatch_suspected', true);
            }),
            'offline' => $q->where('onboarding_status', RouterOnboarding::OFFLINE),
            'legacy_mode' => $q->where(function ($qq) {
                $qq->whereNull('bundle_deployment_mode')
                    ->orWhere('bundle_deployment_mode', '!=', 'bundle');
            }),
            'ready' => $q->where('onboarding_status', RouterOnboarding::READY),
            default => null,
        };

        return $q->paginate(25);
    }

    public function render()
    {
        return view('livewire.admin.router-operations-dashboard');
    }
}
