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
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('subdivision_id')->nullable()->after('customer_id');
            $table->foreignId('phase_id')->nullable()->after('subdivision_id');
            $table->string('block_number')->nullable()->after('dropoff_location');
            $table->string('lot_number')->nullable()->after('block_number');
            $table->decimal('fare_per_passenger', 10, 2)->nullable()->after('total_fare');
            
            $table->foreign('subdivision_id')->references('id')->on('subdivisions');
            $table->foreign('phase_id')->references('id')->on('phases');
            
            $table->index(['subdivision_id', 'phase_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['subdivision_id']);
            $table->dropForeign(['phase_id']);
            $table->dropColumn(['subdivision_id', 'phase_id', 'block_number', 'lot_number', 'fare_per_passenger']);
        });
    }
};
