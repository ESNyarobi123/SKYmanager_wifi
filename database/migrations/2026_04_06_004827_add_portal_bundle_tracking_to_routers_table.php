<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->string('portal_bundle_hash', 64)->nullable()->after('portal_bundle_version');
            $table->string('portal_folder_name', 128)->nullable()->after('portal_bundle_hash');
            $table->timestamp('portal_generated_at')->nullable()->after('portal_folder_name');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'portal_bundle_hash',
                'portal_folder_name',
                'portal_generated_at',
            ]);
        });
    }
};
