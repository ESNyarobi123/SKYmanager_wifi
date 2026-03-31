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
        Schema::create('routers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->string('api_username');
            $table->string('api_password');
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
