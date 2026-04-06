<?php

namespace App\Livewire\Admin;

use App\Models\HotspotPayment;
use App\Models\Router;
use App\Services\AdminRouterRepairService;
use App\Services\HotspotPaymentAuthorizationService;
use App\Services\RouterHealthService;
use App\Support\HotspotPaymentSupportHints;
use App\Support\HotspotPaymentTimeline;
use App\Support\RouterOperationalReadiness;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class HotspotPaymentSupport extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /** @var 'all'|'pending'|'success'|'authorized'|'failed'|'stuck'|'retry_exhausted' */
    public string $filter = 'all';

    public string $search = '';

    public string $flashMessage = '';

    public string $flashType = 'success';

    public ?string $expandedPaymentId = null;

    public function mount(): void
    {
        $this->authorize('hotspot-payments.support');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function retryAuthorization(string $paymentId): void
    {
        $this->authorize('hotspot-payments.support');

        $payment = HotspotPayment::query()->with(['router', 'router.user'])->findOrFail($paymentId);

        try {
            app(HotspotPaymentAuthorizationService::class)->adminRetryAuthorization($payment);
            $this->flashMessage = __('Authorization job re-queued with reset attempts.');
            $this->flashType = 'success';
        } catch (\Throwable $e) {
            $this->flashMessage = $e->getMessage();
            $this->flashType = 'error';
        }
    }

    public function healthThenRetry(string $paymentId): void
    {
        $this->authorize('hotspot-payments.support');

        $payment = HotspotPayment::query()->with('router')->findOrFail($paymentId);
        $router = $payment->router;

        if ($router instanceof Router) {
            app(AdminRouterRepairService::class)->recalculateHealth($router, true);
        }

        $this->retryAuthorization($paymentId);
    }

    public function paginatedPayments()
    {
        $max = (int) config('skymanager.hotspot_authorize_max_attempts', 30);

        $q = HotspotPayment::query()
            ->with(['router.user', 'plan', 'customerPaymentGateway'])
            ->orderByDesc('updated_at');

        if ($this->search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('reference', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('id', 'like', $term)
                    ->orWhereHas('router', function ($rq) use ($term) {
                        $rq->where('name', 'like', $term)->orWhere('id', 'like', $term);
                    });
            });
        }

        match ($this->filter) {
            'pending' => $q->where('status', 'pending'),
            'success' => $q->where('status', 'success'),
            'authorized' => $q->where('status', 'authorized'),
            'failed' => $q->where('status', 'failed'),
            'stuck' => $q->where('status', 'success')
                ->whereNull('authorized_at')
                ->where(function ($qq) {
                    $qq->whereNotNull('last_authorize_error')
                        ->orWhere('authorize_attempts', '>', 0);
                }),
            'retry_exhausted' => $q->where('status', 'success')
                ->whereNull('authorized_at')
                ->where(function ($qq) use ($max) {
                    $qq->where('authorize_attempts', '>=', $max)
                        ->orWhereNotNull('authorize_retry_exhausted_at');
                }),
            default => null,
        };

        return $q->paginate(30);
    }

    /**
     * @return array{readiness: array<string, mixed>, router_online: bool|null}
     */
    public function routerContext(HotspotPayment $payment): array
    {
        $router = $payment->router;
        if (! $router instanceof Router) {
            return ['readiness' => [], 'router_online' => null];
        }

        return [
            'readiness' => RouterOperationalReadiness::snapshot($router),
            'router_online' => $router->is_online,
        ];
    }

    public function toggleExpand(string $paymentId): void
    {
        $this->expandedPaymentId = $this->expandedPaymentId === $paymentId ? null : $paymentId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function liveHealthEvaluate(HotspotPayment $payment): ?array
    {
        $router = $payment->router;
        if (! $router instanceof Router) {
            return null;
        }

        return app(RouterHealthService::class)->evaluate($router, false);
    }

    /**
     * @return list<string>
     */
    public function supportHints(HotspotPayment $payment): array
    {
        $live = $this->liveHealthEvaluate($payment);

        return HotspotPaymentSupportHints::forPayment($payment, $payment->router, $live);
    }

    /**
     * @return list<array{at: ?string, label: string, detail: string}>
     */
    public function paymentTimeline(HotspotPayment $payment): array
    {
        return HotspotPaymentTimeline::build($payment);
    }

    public function render()
    {
        return view('livewire.admin.hotspot-payment-support');
    }
}
