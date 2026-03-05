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
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('status');
        });
        
        Schema::table('booking_riders', function (Blueprint $table) {
            $table->enum('status', ['assigned', 'accepted', 'expired', 'rejected', 'completed'])->default('assigned');
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_riders', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('status');
        });
        
        Schema::table('booking_riders', function (Blueprint $table) {
            $table->enum('status', ['assigned', 'accepted', 'expired', 'rejected'])->default('assigned');
            $table->index(['status', 'expires_at']);
        });
    }
};
