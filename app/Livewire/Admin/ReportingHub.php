<?php

namespace App\Livewire\Admin;

use App\Services\OperationsReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReportingHub extends Component
{
    use AuthorizesRequests;

    /** @var 'revenue'|'hotspot'|'routers'|'plans'|'incidents' */
    public string $reportType = 'revenue';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorize('reports.view');
        $this->dateFrom = $this->dateFrom ?: now()->subDays(30)->toDateString();
        $this->dateTo = $this->dateTo ?: now()->toDateString();
    }

    public function updatedDateFrom(): void
    {
        unset($this->rows);
    }

    public function updatedDateTo(): void
    {
        unset($this->rows);
    }

    public function updatedReportType(): void
    {
        unset($this->rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): Collection
    {
        $actor = auth()->user();
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();
        $reports = app(OperationsReportService::class);

        return match ($this->reportType) {
            'revenue' => $reports->revenueReport($actor, $from, $to),
            'hotspot' => $reports->hotspotPaymentReport($actor, $from, $to),
            'routers' => $reports->routerOperationsReport($actor),
            'plans' => $reports->planPerformanceReport($actor, $from, $to),
            'incidents' => collect([$reports->supportIncidentsReport($actor)]),
            default => collect(),
        };
    }

    public function exportUrl(string $type): string
    {
        return route('admin.exports.download', [
            'type' => $type,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.reporting-hub');
    }
}
