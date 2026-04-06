<?php

namespace App\Livewire\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class SupportExportCenter extends Component
{
    use AuthorizesRequests;

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorize('reports.export');
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public function exportPresets(): array
    {
        return [
            ['key' => 'revenue', 'label' => __('Revenue (subscriptions)'), 'description' => __('Successful and pending subscription payments in range.')],
            ['key' => 'hotspot_payments', 'label' => __('Hotspot payments'), 'description' => __('Captive portal payments with authorize lifecycle fields.')],
            ['key' => 'router_operations', 'label' => __('Router operations snapshot'), 'description' => __('Current onboarding, health, and bundle mode per router.')],
            ['key' => 'plan_performance', 'label' => __('Plan performance'), 'description' => __('Purchases and authorization outcomes grouped by billing plan.')],
            ['key' => 'support_incidents', 'label' => __('Support / incidents summary'), 'description' => __('Single-row aggregate: stuck payments, tunnel issues, recovery rate.')],
            ['key' => 'invoices', 'label' => __('Invoices'), 'description' => __('Invoices issued in range (admin: all customers).')],
            ['key' => 'vouchers', 'label' => __('Customer vouchers'), 'description' => __('Voucher batches created in range.')],
        ];
    }

    public function href(string $type): string
    {
        return route('admin.exports.download', [
            'type' => $type,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.support-export-center');
    }
}
