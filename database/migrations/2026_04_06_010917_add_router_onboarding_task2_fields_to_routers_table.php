<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('onboarding_status');
            $table->timestamp('script_generated_at')->nullable()->after('claimed_at');
            $table->timestamp('script_downloaded_at')->nullable()->after('script_generated_at');
            $table->timestamp('script_applied_at')->nullable()->after('script_downloaded_at');
            $table->timestamp('last_api_check_at')->nullable()->after('last_api_success_at');
            $table->timestamp('last_portal_check_at')->nullable()->after('last_tunnel_check_at');
            $table->timestamp('ready_at')->nullable()->after('last_portal_check_at');
            $table->string('last_error_code', 64)->nullable()->after('ready_at');
            $table->text('last_error_message')->nullable()->after('last_error_code');
            $table->json('onboarding_warnings')->nullable()->after('last_error_message');
            $table->string('wan_interface', 32)->nullable()->after('hotspot_network');
            $table->string('wifi_interface', 32)->nullable()->after('wan_interface');
            $table->string('preferred_vpn_mode', 16)->default('wireguard')->after('wifi_interface');
            $table->string('router_model', 64)->nullable()->after('preferred_vpn_mode');
            $table->string('routeros_version_hint', 24)->nullable()->after('router_model');
            $table->boolean('use_default_network_settings')->default(true)->after('routeros_version_hint');
            $table->string('bundle_deployment_mode', 16)->nullable()->after('use_default_network_settings');
            $table->unsignedInteger('api_credential_version')->default(0)->after('api_password');
            $table->timestamp('api_credentials_updated_at')->nullable()->after('api_credential_version');
            $table->boolean('credential_mismatch_suspected')->default(false)->after('api_credentials_updated_at');
            $table->timestamp('wg_last_handshake_at')->nullable()->after('credential_mismatch_suspected');
            $table->string('last_known_api_username', 64)->nullable()->after('wg_last_handshake_at');
            $table->json('health_snapshot')->nullable()->after('last_known_api_username');
            $table->timestamp('health_evaluated_at')->nullable()->after('health_snapshot');
        });

        DB::table('routers')->whereNull('claimed_at')->update([
            'claimed_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'claimed_at',
                'script_generated_at',
                'script_downloaded_at',
                'script_applied_at',
                'last_api_check_at',
                'last_portal_check_at',
                'ready_at',
                'last_error_code',
                'last_error_message',
                'onboarding_warnings',
                'wan_interface',
                'wifi_interface',
                'preferred_vpn_mode',
                'router_model',
                'routeros_version_hint',
                'use_default_network_settings',
                'bundle_deployment_mode',
                'api_credential_version',
                'api_credentials_updated_at',
                'credential_mismatch_suspected',
                'wg_last_handshake_at',
                'last_known_api_username',
                'health_snapshot',
                'health_evaluated_at',
            ]);
        });
    }
};
