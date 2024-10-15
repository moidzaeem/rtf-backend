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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Link to provider services
            $table->foreignId('provider_service_id')->constrained()->onDelete('cascade'); // Link to provider services
            $table->date('booking_date');
            $table->time('booking_time');
            $table->string('payment_status')->default('not_paid');
            $table->string('payment');
            $table->string('booking_status')->default('created');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
