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
        Schema::create('kontrol_sirams', function (Blueprint $table) {
            $table->id('id_kontrol_siram');
            $table->foreignId('id_sensor')->constrained('sensors', 'id_sensor')->onDelete('cascade');
            $table->boolean('mode_auto')->default(true);
            $table->boolean('status_pompa')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kontrol_sirams');
    }
};
