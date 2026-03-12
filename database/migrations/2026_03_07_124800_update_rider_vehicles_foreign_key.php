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
        Schema::table('rider_vehicles', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign(['rider_id']);
            // Change the foreign key to users.id
            $table->foreign('rider_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rider_vehicles', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['rider_id']);
            // Restore the old foreign key to riders.id
            $table->foreign('rider_id')->references('id')->on('riders')->onDelete('cascade');
        });
    }
};
