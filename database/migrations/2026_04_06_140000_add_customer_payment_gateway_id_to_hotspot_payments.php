<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_payments', function (Blueprint $table) {
            $table->foreignUlid('customer_payment_gateway_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('customer_payment_gateways')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_payments', function (Blueprint $table) {
            $table->dropForeign(['customer_payment_gateway_id']);
            $table->dropColumn('customer_payment_gateway_id');
        });
    }
};
