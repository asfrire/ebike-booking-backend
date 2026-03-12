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
        Schema::table('booking_riders', function (Blueprint $table) {
            // Drop the old foreign key to riders
            $table->dropForeign(['rider_id']);
            // Add new foreign key to users
            $table->foreign('rider_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_riders', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['rider_id']);
            // Add back the old foreign key to riders
            $table->foreign('rider_id')->references('id')->on('riders')->onDelete('cascade');
        });
    }
};
