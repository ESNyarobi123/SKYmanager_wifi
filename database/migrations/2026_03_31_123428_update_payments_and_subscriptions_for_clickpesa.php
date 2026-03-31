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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('reference')->index();
            $table->string('provider', 50)->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
            $table->enum('provider', ['M-Pesa', 'Tigo'])->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['active', 'expired'])->default('active')->change();
        });
    }
};
