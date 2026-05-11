<?php

use App\Http\Controllers\Api\FaceController;
use App\Http\Controllers\Api\GuruController;
use App\Http\Controllers\Api\IotController;
use App\Http\Controllers\Api\JadwalMengajarController;
use App\Http\Controllers\Api\MasterKelasController;
use App\Http\Controllers\Api\PresensiController;
use App\Http\Controllers\Api\SiswaController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\IotAdminController;

Route::middleware(['bearer.token'])->group(function (): void {
    // Face Recognition Engine
    Route::post('/face/register', [FaceController::class, 'register'])->middleware('throttle:face-api');
    Route::get('/face/landmark', [FaceController::class, 'landmark']);
    
    // CRUD Data Master
    Route::apiResource('siswa', SiswaController::class);
    Route::apiResource('guru', GuruController::class);
    Route::apiResource('master-kelas', MasterKelasController::class);
    Route::apiResource('jadwal', JadwalMengajarController::class);
    
    // Presensi Logs
    Route::apiResource('presensi', PresensiController::class)->only(['index', 'show', 'update', 'destroy']);

    // IoT Admin
    Route::get('/iot-admin/devices', [IotAdminController::class, 'devices']);
    Route::get('/iot-admin/sessions', [IotAdminController::class, 'sessions']);
    Route::get('/iot-admin/candidates', [IotAdminController::class, 'candidates']);
    Route::post('/iot-admin/session/start', [IotAdminController::class, 'startSession']);
    Route::post('/iot-admin/session/{id}/cancel', [IotAdminController::class, 'cancelSession']);
    Route::post('/iot-admin/session/{id}/save', [IotAdminController::class, 'saveSession']);
    Route::post('/iot-admin/candidates/map', [IotAdminController::class, 'savePemetaan']);
});

// IoT Device Endpoints (no bearer.token middleware since it uses X-Device-Token inside controller)
Route::prefix('iot')->group(function () {
    Route::get('health', [IotController::class, 'health']);
    Route::post('scan', [IotController::class, 'scan']);
    Route::post('device/heartbeat', [IotController::class, 'deviceHeartbeat']);
    Route::get('device/command', [IotController::class, 'deviceCommand']);
    Route::post('register/capture', [IotController::class, 'registerCapture']);
});
