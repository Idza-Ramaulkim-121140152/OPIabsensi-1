<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\FaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['bearer.token', 'throttle:face-api'])->group(function (): void {
    Route::post('/face/register', [FaceController::class, 'register']);
    Route::post('/face/attendance', [FaceController::class, 'attendance']);
});

Route::middleware('bearer.token')->get('/attendance', [AttendanceController::class, 'index']);
