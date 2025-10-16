    
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LicenseController;

// License Routes (no middleware)
Route::get('/license/expired', [LicenseController::class, 'expired'])->name('license.expired');
Route::get('/license', [LicenseController::class, 'index'])->name('license.index');
Route::post('/license/activate', [LicenseController::class, 'activate'])->name('license.activate');

// Protected Routes (require valid license or active trial)
Route::middleware(['license'])->group(function () {
    // Admin Routes
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.settings');
    Route::post('/admin/settings', [AdminController::class, 'update'])->name('admin.settings.update');
    Route::get('/devices/{hostname}/summary', [DeviceController::class, 'summary'])->name('devices.summary');
    // Web Routes - Devices
    Route::get('/', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/{hostname}', [DeviceController::class, 'show'])->name('devices.show');
    Route::get('/devices/{hostname}/events', [DeviceController::class, 'events'])->name('devices.events');
    Route::delete('/devices/{hostname}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    // Quick summary JSON for device (AJAX)
    Route::get('/devices/{hostname}/timeline', [DeviceController::class, 'timeline'])->name('devices.timeline');
    // (Realtime update routes removed - timeline is static)
    Route::get('/devices/{hostname}/quick_summary', [DeviceController::class, 'quickSummary'])->name('devices.quick_summary');
    // Blocked sites management (web)
    Route::post('/devices/{hostname}/blocked_sites', [DeviceController::class, 'addBlockedSite'])->name('devices.blocked.add');
    Route::delete('/devices/{hostname}/blocked_sites/{id}', [DeviceController::class, 'removeBlockedSite'])->name('devices.blocked.remove');
});

// API Routes - Events
Route::post('/api/events', [EventController::class, 'store']);

// API Routes - Blocked Sites
Route::get('/api/blocked_sites', [\App\Http\Controllers\Api\BlockedSiteController::class, 'index']);

// API Routes - Dashboard (keep for backwards compatibility)
Route::prefix('api/dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/devices', [DashboardController::class, 'devices']);
    Route::get('/activity_timeline', [DashboardController::class, 'activityTimeline']);
    Route::get('/top_domains', [DashboardController::class, 'topDomains']);
    Route::get('/recent_events', [DashboardController::class, 'recentEvents']);
    Route::get('/device_activity', [DashboardController::class, 'deviceActivity']);
});
