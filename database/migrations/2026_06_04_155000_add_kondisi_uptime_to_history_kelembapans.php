<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_kelembapans', function (Blueprint $table) {
            if (!Schema::hasColumn('history_kelembapans', 'kondisi')) {
                $table->string('kondisi')->default('UNKNOWN')->after('kelembapan');
            }
            if (!Schema::hasColumn('history_kelembapans', 'uptime')) {
                $table->bigInteger('uptime')->default(0)->after('kondisi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('history_kelembapans', function (Blueprint $table) {
            if (Schema::hasColumn('history_kelembapans', 'kondisi')) {
                $table->dropColumn('kondisi');
            }
            if (Schema::hasColumn('history_kelembapans', 'uptime')) {
                $table->dropColumn('uptime');
            }
        });
    }
};
