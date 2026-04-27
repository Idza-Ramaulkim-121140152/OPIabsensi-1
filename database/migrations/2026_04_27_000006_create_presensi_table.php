<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi', function (Blueprint $table) {
            $table->id('id_presensi');
            $table->unsignedBigInteger('id_siswa')->nullable();
            $table->unsignedBigInteger('id_guru')->nullable();
            $table->unsignedBigInteger('id_jadwal')->nullable();
            $table->string('kelas', 50)->nullable();
            $table->date('tanggal');
            $table->time('jam');
            $table->string('status', 20)->default('hadir');
            $table->string('metode', 20)->default('manual');
            $table->string('catatan', 255)->nullable();
            $table->double('confidence')->nullable();
            $table->timestamps();

            $table->foreign('id_siswa')->references('id')->on('siswa')->nullOnDelete();
            $table->foreign('id_guru')->references('id_guru')->on('guru')->nullOnDelete();
            $table->foreign('id_jadwal')->references('id_jadwal')->on('jadwal_mengajar')->nullOnDelete();
            
            $table->index(['id_siswa', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};
