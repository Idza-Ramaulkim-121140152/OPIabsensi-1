<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_devices', function (Blueprint $table) {
            $table->id('id_device');
            $table->string('device_code', 100)->unique();
            $table->string('device_name', 150)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('status_mode', 30)->default('attendance');
            $table->dateTime('last_seen_at')->nullable()->index();
            $table->string('last_ip', 45)->nullable();
            $table->string('last_message', 255)->nullable();
            $table->string('firmware_version', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('iot_registration_sessions', function (Blueprint $table) {
            $table->id('id_session');
            $table->foreignId('device_id')->constrained('iot_devices', 'id_device')->cascadeOnDelete();
            $table->string('session_token', 80)->unique();
            $table->string('status', 30)->default('waiting_device')->index();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('target_type', 20)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('captured_rfid', 120)->nullable();
            $table->longText('captured_face')->nullable();
            $table->dateTime('captured_at')->nullable();
            $table->dateTime('command_issued_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('iot_registration_candidates', function (Blueprint $table) {
            $table->id('id_candidate');
            $table->string('nama_registrasi', 150);
            $table->string('id_rfid', 120)->nullable()->index();
            $table->longText('foto_wajah')->nullable();
            $table->unsignedBigInteger('source_session_id')->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('mapped_target_type', 20)->nullable();
            $table->unsignedBigInteger('mapped_target_id')->nullable();
            $table->dateTime('mapped_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('iot_scan_logs', function (Blueprint $table) {
            $table->id('id_scan');
            $table->string('entity_type', 20)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('rfid_uid', 100)->nullable();
            $table->unsignedBigInteger('expected_user_id')->nullable();
            $table->unsignedBigInteger('matched_user_id')->nullable();
            $table->string('gateway_status', 20)->nullable();
            $table->decimal('confidence', 6, 4)->nullable();
            $table->string('result', 20)->index();
            $table->string('message', 255)->nullable();
            $table->dateTime('request_time')->index();
            $table->longText('raw_response')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_scan_logs');
        Schema::dropIfExists('iot_registration_candidates');
        Schema::dropIfExists('iot_registration_sessions');
        Schema::dropIfExists('iot_devices');
    }
};
