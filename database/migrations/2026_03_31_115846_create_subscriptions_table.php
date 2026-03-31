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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('wifi_user_id')->constrained('wifi_users')->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained('billing_plans')->cascadeOnDelete();
            $table->foreignUlid('router_id')->constrained('routers')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->enum('status', ['active', 'expired'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
