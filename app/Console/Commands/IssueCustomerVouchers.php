<?php

namespace App\Console\Commands;

use App\Models\CustomerBillingPlan;
use App\Models\CustomerVoucher;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('skymanager:issue-customer-vouchers {customer_id : Owner user ULID} {plan_id : CustomerBillingPlan ULID} {count=10 : Number of codes} {--batch=} {--prefix=}')]
#[Description('Generate prepaid voucher codes for a customer billing plan (captive portal / local API).')]
class IssueCustomerVouchers extends Command
{
    public function handle(): int
    {
        $customer = User::find($this->argument('customer_id'));

        if (! $customer) {
            $this->error('Customer not found.');

            return self::FAILURE;
        }

        $plan = CustomerBillingPlan::where('id', $this->argument('plan_id'))
            ->where('customer_id', $customer->id)
            ->first();

        if (! $plan) {
            $this->error('Plan not found for this customer.');

            return self::FAILURE;
        }

        $count = max(1, min(500, (int) $this->argument('count')));
        $batch = $this->option('batch') ?: 'CLI '.now()->toDateTimeString();
        $prefix = $this->option('prefix');

        $vouchers = CustomerVoucher::generateBatch(
            $customer->id,
            $plan->id,
            $count,
            $batch,
            $prefix ?: null,
            null
        );

        $this->info("Issued {$vouchers->count()} voucher(s) for plan \"{$plan->name}\".");

        foreach ($vouchers as $v) {
            $this->line($v->code);
        }

        return self::SUCCESS;
    }
}
