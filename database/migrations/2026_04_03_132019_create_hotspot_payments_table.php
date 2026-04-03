<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hotspot_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('router_id', 26)->index();
            $table->string('plan_id', 26)->index();
            $table->string('client_mac', 17);
            $table->string('client_ip', 45);
            $table->string('phone', 20);
            $table->decimal('amount', 10, 2);
            $table->string('reference', 64)->unique();
            $table->string('transaction_id', 100)->nullable()->index();
            $table->enum('status', ['pending', 'success', 'failed', 'authorized'])->default('pending');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspot_payments');
    }
};
