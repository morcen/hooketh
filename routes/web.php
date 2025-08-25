<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Health check endpoint for deployment monitoring
Route::get('/health', function () {
    $health = [
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'services' => []
    ];
    
    // Check database connection
    try {
        DB::connection()->getPdo();
        $health['services']['database'] = 'connected';
    } catch (Exception $e) {
        $health['status'] = 'error';
        $health['services']['database'] = 'disconnected';
    }
    
    // Check Redis connection
    try {
        Redis::ping();
        $health['services']['redis'] = 'connected';
    } catch (Exception $e) {
        $health['status'] = 'error';
        $health['services']['redis'] = 'disconnected';
    }
    
    // Check PHP extensions
    $requiredExtensions = ['pgsql', 'pdo_pgsql', 'redis'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $extension) {
        if (!extension_loaded($extension)) {
            $missingExtensions[] = $extension;
        }
    }
    
    if (!empty($missingExtensions)) {
        $health['status'] = 'error';
        $health['missing_extensions'] = $missingExtensions;
    }
    
    $health['extensions'] = [
        'pgsql' => extension_loaded('pgsql'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'redis' => extension_loaded('redis'),
    ];
    
    $status = $health['status'] === 'ok' ? 200 : 503;
    return response()->json($health, $status);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/endpoints', [DashboardController::class, 'endpoints'])->name('endpoints');
    Route::get('/events', [DashboardController::class, 'events'])->name('events');
    Route::get('/deliveries', [DashboardController::class, 'deliveries'])->name('deliveries');
});
