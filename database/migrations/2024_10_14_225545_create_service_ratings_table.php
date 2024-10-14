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
        Schema::create('service_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_service_id')->constrained()->onDelete('cascade'); // Link to provider services
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Link to users giving the rating
            $table->tinyInteger('rating'); // Rating value, e.g., 1-5
            $table->text('comment')->nullable(); // Optional comment
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_ratings');
    }
};
