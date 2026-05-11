<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            if (! Schema::hasColumn('presensi', 'shift_name')) {
                $table->string('shift_name', 100)->nullable()->after('metode');
            }

            if (! Schema::hasColumn('presensi', 'shift_status')) {
                $table->string('shift_status', 30)->nullable()->after('shift_name')->index();
            }
        });

        DB::statement('ALTER TABLE jadwal_mengajar MODIFY hari VARCHAR(120) NOT NULL');
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            if (Schema::hasColumn('presensi', 'shift_status')) {
                $table->dropColumn('shift_status');
            }

            if (Schema::hasColumn('presensi', 'shift_name')) {
                $table->dropColumn('shift_name');
            }
        });

        DB::statement('ALTER TABLE jadwal_mengajar MODIFY hari VARCHAR(10) NOT NULL');
    }
};
