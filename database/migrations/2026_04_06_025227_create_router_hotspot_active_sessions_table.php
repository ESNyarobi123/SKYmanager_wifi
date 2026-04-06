<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_hotspot_active_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('router_id')->constrained()->cascadeOnDelete();
            $table->string('mikrotik_internal_id', 48);
            $table->string('mac_address', 24)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_name', 191)->nullable();
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->unsignedInteger('uptime_seconds')->nullable();
            $table->string('uptime_raw', 64)->nullable();
            $table->timestamp('synced_at')->index();
            $table->timestamps();

            $table->index(['router_id', 'mac_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_hotspot_active_sessions');
    }
};
