<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_payments', function (Blueprint $table) {
            $table->timestamp('provider_confirmed_at')->nullable()->after('status');
            $table->unsignedSmallInteger('authorize_attempts')->default(0)->after('provider_confirmed_at');
            $table->text('last_authorize_error')->nullable()->after('authorize_attempts');
            $table->timestamp('authorization_job_dispatched_at')->nullable()->after('last_authorize_error');
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_payments', function (Blueprint $table) {
            $table->dropColumn([
                'provider_confirmed_at',
                'authorize_attempts',
                'last_authorize_error',
                'authorization_job_dispatched_at',
            ]);
        });
    }
};
