<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Monitoring\MonitoringController;
use App\Http\Controllers\Parameter\ParameterController;
use App\Http\Controllers\Riwayat\RiwayatController;
use App\Http\Controllers\Notifikasi\NotifikasiController;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/', [MonitoringController::class, 'index'])->name('kontrol');
        Route::patch('/{id}/toggle-mode', [MonitoringController::class, 'toggleMode'])->name('toggle-mode');
        Route::post('/{id}/nyalakan', [MonitoringController::class, 'nyalakan'])->name('nyalakan');
        Route::post('/{id}/matikan', [MonitoringController::class, 'matikan'])->name('matikan');
    });

    Route::prefix('parameter')->name('parameter.')->group(function () {
        Route::get('/', [ParameterController::class, 'index'])->name('index');
        Route::get('/{id}/edit', [ParameterController::class, 'edit'])->name('edit');
        Route::patch('/{id}', [ParameterController::class, 'update'])->name('update');
    });

    Route::prefix('riwayat')->name('riwayat.')->group(function () {
        Route::get('/', [RiwayatController::class, 'index'])->name('index');
        Route::get('/export', [RiwayatController::class, 'export'])->name('export');
    });

    Route::prefix('history-kelembapan')->name('history-kelembapan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\HistoryKelembapanController::class, 'index'])->name('index');
    });

    Route::prefix('notifikasi')->name('notifikasi.')->group(function () {
        Route::get('/', [NotifikasiController::class, 'index'])->name('index');
        Route::post('/tandai-semua', [NotifikasiController::class, 'tandaiSemua'])->name('tandai-semua');
    });
    
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
