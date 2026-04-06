<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_vouchers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('customer_billing_plan_id', 26)->index();
            $table->string('code', 40)->unique();
            $table->string('batch_name', 120);
            $table->string('status', 24)->default('unused')->index();
            $table->string('used_by_mac', 17)->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_billing_plan_id')
                ->references('id')
                ->on('customer_billing_plans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vouchers');
    }
};
