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
        Schema::table('provider_details', function (Blueprint $table) {
            $table->string('country')->nullable();
            $table->string('bg_image')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_details', function (Blueprint $table) {
            $table->dropColumn('country');
            $table->dropColumn('bg_image');
        });
    }
};
