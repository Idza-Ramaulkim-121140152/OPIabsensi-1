<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guru', function (Blueprint $table) {
            $table->id('id_guru');
            $table->string('nama', 150);
            $table->string('nip', 50)->unique();
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->string('kelas_wali', 50)->nullable();
            $table->boolean('is_wali_kelas')->default(false);
            $table->string('id_rfid', 100)->unique()->nullable();
            $table->longText('foto_wajah')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guru');
    }
};
