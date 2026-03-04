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
        Schema::create('booking_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('rider_id')->constrained()->onDelete('cascade');
            $table->integer('allocated_seats');
            $table->enum('status', ['assigned', 'accepted', 'expired', 'rejected'])->default('assigned');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['status', 'expires_at']);
            $table->unique(['booking_id', 'rider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_riders');
    }
};
