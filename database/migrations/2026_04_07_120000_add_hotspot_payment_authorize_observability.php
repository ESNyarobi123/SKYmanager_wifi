<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_payments', function (Blueprint $table) {
            $table->timestamp('first_authorize_failure_at')->nullable()->after('authorization_job_dispatched_at');
            $table->timestamp('last_authorize_failed_at')->nullable()->after('first_authorize_failure_at');
            $table->string('last_authorize_error_code', 64)->nullable()->after('last_authorize_failed_at');
            $table->json('last_authorize_health_snapshot')->nullable()->after('last_authorize_error_code');
            $table->json('last_authorize_attempt_context')->nullable()->after('last_authorize_health_snapshot');
            $table->boolean('last_failure_router_online')->nullable()->after('last_authorize_attempt_context');
            $table->string('last_failure_overall_health', 32)->nullable()->after('last_failure_router_online');
            $table->string('last_failure_tunnel_level', 32)->nullable()->after('last_failure_overall_health');
            $table->string('last_failure_api_level', 32)->nullable()->after('last_failure_tunnel_level');
            $table->string('last_failure_portal_level', 32)->nullable()->after('last_failure_api_level');
            $table->boolean('router_ready_for_authorize_at_failure')->nullable()->after('last_failure_portal_level');
            $table->boolean('provider_confirmed_at_failure')->nullable()->after('router_ready_for_authorize_at_failure');
            $table->timestamp('authorize_retry_exhausted_at')->nullable()->after('provider_confirmed_at_failure');
            $table->timestamp('recovered_after_failure_at')->nullable()->after('authorize_retry_exhausted_at');
            $table->unsignedSmallInteger('failed_authorize_attempts_before_success')->nullable()->after('recovered_after_failure_at');
            $table->unsignedInteger('seconds_to_recover_from_first_failure')->nullable()->after('failed_authorize_attempts_before_success');
            $table->unsignedSmallInteger('admin_authorize_retry_count')->default(0)->after('seconds_to_recover_from_first_failure');
            $table->timestamp('last_admin_authorize_retry_at')->nullable()->after('admin_authorize_retry_count');
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_payments', function (Blueprint $table) {
            $table->dropColumn([
                'first_authorize_failure_at',
                'last_authorize_failed_at',
                'last_authorize_error_code',
                'last_authorize_health_snapshot',
                'last_authorize_attempt_context',
                'last_failure_router_online',
                'last_failure_overall_health',
                'last_failure_tunnel_level',
                'last_failure_api_level',
                'last_failure_portal_level',
                'router_ready_for_authorize_at_failure',
                'provider_confirmed_at_failure',
                'authorize_retry_exhausted_at',
                'recovered_after_failure_at',
                'failed_authorize_attempts_before_success',
                'seconds_to_recover_from_first_failure',
                'admin_authorize_retry_count',
                'last_admin_authorize_retry_at',
            ]);
        });
    }
};
