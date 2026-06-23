<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('password');
            }
        });

        Schema::table('notifikasis', function (Blueprint $table) {
            if (!Schema::hasColumn('notifikasis', 'dibaca')) {
                $table->boolean('dibaca')->default(false)->after('isi_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifikasis', function (Blueprint $table) {
            if (Schema::hasColumn('notifikasis', 'dibaca')) {
                $table->dropColumn('dibaca');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
