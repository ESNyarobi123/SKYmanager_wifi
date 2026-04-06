<?php

namespace App\Livewire\Customer;

use App\Data\ClientSessionView;
use App\Models\User;
use App\Services\CustomerClientSessionService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.customer')]
class ClientSessions extends Component
{
    use WithPagination;

    public string $tab = 'active';

    public ?string $routerId = null;

    public string $search = '';

    public string $sourceType = 'all';

    public string $planKey = '';

    /** all | valid | pending | none */
    public string $access = 'all';

    public ?string $historyFrom = null;

    public ?string $historyTo = null;

    protected string $paginationTheme = 'tailwind';

    public function mount(?string $router = null): void
    {
        $q = request()->query('router');
        if (is_string($q) && $q !== '') {
            $this->routerId = $q;
        } elseif ($router) {
            $this->routerId = $router;
        }

        $tab = request()->query('tab');
        if (in_array($tab, ['active', 'history'], true)) {
            $this->tab = $tab;
        }

        $access = request()->query('access');
        if (in_array($access, ['all', 'valid', 'pending', 'none'], true)) {
            $this->access = $access;
        }
    }

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function updatingRouterId(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSourceType(): void
    {
        $this->resetPage();
    }

    public function updatingPlanKey(): void
    {
        $this->resetPage();
    }

    public function updatingAccess(): void
    {
        $this->resetPage();
    }

    public function updatingHistoryFrom(): void
    {
        $this->resetPage();
    }

    public function updatingHistoryTo(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function customer(): User
    {
        return Auth::user();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function planOptions(): array
    {
        return app(CustomerClientSessionService::class)->planOptionsForCustomer($this->customer);
    }

    /**
     * @return LengthAwarePaginator<int, ClientSessionView>
     */
    #[Computed]
    public function sessionsPage(): LengthAwarePaginator
    {
        $service = app(CustomerClientSessionService::class);

        $historyFrom = $this->parseDate($this->historyFrom);
        $historyTo = $this->parseDate($this->historyTo);

        $collection = $service->sessionsForCustomer($this->customer, [
            'tab' => $this->tab,
            'router_id' => $this->routerId ?: null,
            'search' => $this->search,
            'source_type' => $this->sourceType,
            'plan_key' => $this->planKey ?: null,
            'access' => $this->access,
            'history_from' => $this->tab === 'history' ? $historyFrom : null,
            'history_to' => $this->tab === 'history' ? $historyTo : null,
        ]);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 20;

        return new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    public function render()
    {
        return view('livewire.customer.client-sessions', [
            'sessions' => $this->sessionsPage,
            'routers' => $this->customer->routers()->orderBy('name')->get(),
        ]);
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
