<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            if (! Schema::hasColumn('presensi', 'shift_type')) {
                $table->string('shift_type', 10)->nullable()->after('shift_status');
            }

            if (! Schema::hasColumn('presensi', 'shift_index')) {
                $table->unsignedSmallInteger('shift_index')->nullable()->after('shift_type');
            }
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->unique(
                ['id_siswa', 'tanggal', 'id_jadwal', 'shift_index', 'shift_type'],
                'presensi_shift_entry_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropUnique('presensi_shift_entry_unique');
        });

        Schema::table('presensi', function (Blueprint $table) {
            if (Schema::hasColumn('presensi', 'shift_index')) {
                $table->dropColumn('shift_index');
            }

            if (Schema::hasColumn('presensi', 'shift_type')) {
                $table->dropColumn('shift_type');
            }
        });
    }
};
