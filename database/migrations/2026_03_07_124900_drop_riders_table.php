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
        Schema::dropIfExists('riders');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the riders table if needed, but since it's replaced, perhaps no down
    }
};
