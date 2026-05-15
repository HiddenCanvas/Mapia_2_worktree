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
        Schema::create('history_kelembapans', function (Blueprint $table) {
            $table->id('id_history');
            $table->foreignId('id_sensor')->constrained('sensors', 'id_sensor')->onDelete('cascade');
            $table->float('kelembapan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_kelembapans');
    }
};
