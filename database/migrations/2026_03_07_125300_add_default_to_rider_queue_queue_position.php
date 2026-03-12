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
        Schema::table('rider_queue', function (Blueprint $table) {
            $table->string('queue_position')->default('stand by')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rider_queue', function (Blueprint $table) {
            $table->string('queue_position')->default(null)->change();
        });
    }
};
