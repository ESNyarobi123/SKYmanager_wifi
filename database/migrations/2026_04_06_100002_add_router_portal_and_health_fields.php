<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->string('local_portal_token', 64)->nullable()->after('last_seen');
            $table->string('onboarding_status', 32)->default('claimed')->after('local_portal_token');
            $table->string('portal_bundle_version', 32)->nullable()->after('onboarding_status');
            $table->timestamp('last_api_success_at')->nullable()->after('portal_bundle_version');
            $table->text('last_api_error')->nullable()->after('last_api_success_at');
            $table->timestamp('last_tunnel_check_at')->nullable()->after('last_api_error');
            $table->boolean('last_tunnel_ok')->nullable()->after('last_tunnel_check_at');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'local_portal_token',
                'onboarding_status',
                'portal_bundle_version',
                'last_api_success_at',
                'last_api_error',
                'last_tunnel_check_at',
                'last_tunnel_ok',
            ]);
        });
    }
};
