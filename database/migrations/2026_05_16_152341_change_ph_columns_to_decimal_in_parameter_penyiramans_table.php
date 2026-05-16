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
        Schema::table('parameter_penyiramans', function (Blueprint $table) {
            // Mengubah tipe data bigint ke decimal agar bisa menyimpan angka desimal (PH)
            // decimal(4, 2) artinya total 4 digit, dengan 2 digit di belakang koma (contoh: 14.00)
            $table->decimal('min_ph', 4, 2)->change();
            $table->decimal('max_ph', 4, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parameter_penyiramans', function (Blueprint $table) {
            $table->bigInteger('min_ph')->change();
            $table->bigInteger('max_ph')->change();
        });
    }
};
