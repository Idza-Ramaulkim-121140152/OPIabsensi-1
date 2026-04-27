<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('no_induk', 50)->unique()->nullable();
            $table->string('kelas', 50)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('id_rfid', 100)->unique()->nullable();
            $table->longText('foto_wajah')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
