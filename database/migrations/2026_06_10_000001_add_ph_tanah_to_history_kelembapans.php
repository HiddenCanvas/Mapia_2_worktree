<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_kelembapans', function (Blueprint $table) {
            if (!Schema::hasColumn('history_kelembapans', 'ph_tanah')) {
                $table->float('ph_tanah')->default(7.0)->after('kelembapan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('history_kelembapans', function (Blueprint $table) {
            if (Schema::hasColumn('history_kelembapans', 'ph_tanah')) {
                $table->dropColumn('ph_tanah');
            }
        });
    }
};