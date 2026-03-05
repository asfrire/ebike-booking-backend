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
        Schema::create('rider_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->onDelete('cascade');
            $table->timestamp('time_in');
            $table->timestamp('time_out')->nullable();
            $table->integer('total_minutes')->nullable();
            $table->timestamps();
            
            $table->index(['rider_id', 'time_in']);
            $table->index(['rider_id', 'time_out']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_sessions');
    }
};
