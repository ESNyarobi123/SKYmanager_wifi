<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->timestamp('hotspot_sessions_synced_at')->nullable();
            $table->text('hotspot_sessions_sync_error')->nullable();
        });

        Schema::table('hotspot_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('router_bytes_in')->nullable();
            $table->unsignedBigInteger('router_bytes_out')->nullable();
            $table->timestamp('router_usage_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn(['hotspot_sessions_synced_at', 'hotspot_sessions_sync_error']);
        });

        Schema::table('hotspot_payments', function (Blueprint $table) {
            $table->dropColumn(['router_bytes_in', 'router_bytes_out', 'router_usage_synced_at']);
        });
    }
};
