<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SensorController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ════════════════════════════════════════════════════════════
// API v1 — Sensor & Device Control
// ════════════════════════════════════════════════════════════

Route::prefix('v1')->group(function () {
    // Store sensor data (dari ESP32)
    Route::post('/send-data', [SensorController::class, 'store']);

    // Get all sensors
    Route::get('/sensors', [SensorController::class, 'getSensors']);

    // Get detail sensor + latest data
    Route::get('/sensors/{id}', [SensorController::class, 'getSensorDetail']);

    // Update parameter penyiraman
    Route::patch('/sensors/{id}/parameter', [SensorController::class, 'updateParameter']);

    // Update mode (otomatis/manual)
    Route::patch('/sensors/{id}/mode', [SensorController::class, 'updateMode']);

    // Control pump (ON/OFF)
    Route::post('/sensors/{id}/pump', [SensorController::class, 'controlPump']);
});