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
        Schema::table('routers', function (Blueprint $table) {
            $table->string('wg_address')->nullable()->after('ztp_api_password');
            $table->string('hotspot_ssid')->default('PEACE')->after('wg_address');
            $table->string('hotspot_interface')->default('bridge')->after('hotspot_ssid');
            $table->string('hotspot_gateway')->default('192.168.88.1')->after('hotspot_interface');
            $table->string('hotspot_network')->default('192.168.88.0/24')->after('hotspot_gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'wg_address',
                'hotspot_ssid',
                'hotspot_interface',
                'hotspot_gateway',
                'hotspot_network',
            ]);
        });
    }
};
