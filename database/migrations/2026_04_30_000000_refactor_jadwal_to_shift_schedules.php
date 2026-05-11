<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify existing jadwal_mengajar table to support multiple shifts
        Schema::table('jadwal_mengajar', function (Blueprint $table) {
            // Drop old columns that are no longer needed
            $table->dropForeign(['id_guru']);
            $table->dropColumn(['id_guru', 'kelas', 'mata_pelajaran', 'jam_mulai', 'jam_selesai']);
            
            // Add shifts column to store multiple in/out times per day
            // JSON format: [{"nama": "Shift Pagi", "masuk_awal": "07:30", "masuk_akhir": "08:00", "pulang_awal": "12:00", "pulang_akhir": "13:00"}, ...]
            $table->json('shifts')->after('hari')->default('[]');
        });
    }

    public function down(): void
    {
        // Restore old columns if rolling back
        Schema::table('jadwal_mengajar', function (Blueprint $table) {
            $table->dropColumn('shifts');
            
            $table->foreignId('id_guru')->after('id_jadwal')->constrained('guru', 'id_guru')->cascadeOnDelete();
            $table->string('kelas', 50)->after('id_guru');
            $table->string('mata_pelajaran', 120)->after('kelas');
            $table->time('jam_mulai')->after('hari');
            $table->time('jam_selesai')->after('jam_mulai');
        });
    }
};
