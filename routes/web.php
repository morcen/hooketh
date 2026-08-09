<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EndpointController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Computes the full internal health snapshot shared by the public and
// authenticated /health routes below, so both stay in sync with the
// same checks.
$computeHealth = function () {
    $health = [
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'services' => [],
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
        if (! extension_loaded($extension)) {
            $missingExtensions[] = $extension;
        }
    }

    if (! empty($missingExtensions)) {
        $health['status'] = 'error';
        $health['missing_extensions'] = $missingExtensions;
    }

    $health['extensions'] = [
        'pgsql' => extension_loaded('pgsql'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'redis' => extension_loaded('redis'),
    ];

    // Check queue worker via scheduler heartbeat
    $heartbeat = Redis::get('queue:heartbeat');
    $queueStatus = match (true) {
        $heartbeat === null => 'unknown',
        (now()->timestamp - (int) $heartbeat) <= 120 => 'ok',
        default => 'stale',
    };
    $health['services']['queue_worker'] = $queueStatus;
    if ($queueStatus === 'stale') {
        $health['status'] = 'error';
    }

    return $health;
};

// Public health check for load balancers/orchestrators. Deliberately
// unauthenticated (deployment probes rarely carry credentials), so it
// only reports the overall status/HTTP code and never the underlying
// service breakdown, loaded extensions, or queue heartbeat staleness —
// those details are internal infrastructure information and are only
// available via the authenticated /health/detailed route below.
Route::get('/health', function () use ($computeHealth) {
    $health = $computeHealth();
    $status = $health['status'] === 'ok' ? 200 : 503;

    return response()->json([
        'status' => $health['status'],
        'timestamp' => $health['timestamp'],
    ], $status);
});

// Authenticated health check with the full per-service breakdown, for
// ops/monitoring tooling that holds a valid Sanctum token.
Route::middleware('auth:sanctum')->get('/health/detailed', function () use ($computeHealth) {
    $health = $computeHealth();
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
    Route::post('/endpoints', [EndpointController::class, 'store'])->name('endpoints.store');
    Route::match(['put', 'patch'], '/endpoints/{endpoint}', [EndpointController::class, 'update'])->name('endpoints.update');
    Route::delete('/endpoints/{endpoint}', [EndpointController::class, 'destroy'])->name('endpoints.destroy');
    Route::post('/endpoints/{endpoint}/test', [EndpointController::class, 'test'])
        ->middleware('throttle:webhook-trigger')
        ->name('endpoints.test');
    Route::post('/endpoints/{endpoint}/regenerate-secret', [EndpointController::class, 'regenerateSecret'])
        ->middleware('throttle:secret-rotation')
        ->name('endpoints.regenerate-secret');
    Route::get('/events', [DashboardController::class, 'events'])->name('events');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('/events/{event}/endpoints', [EventController::class, 'syncEndpoints'])->name('events.endpoints');
    Route::post('/events/{event}/trigger', [EventController::class, 'trigger'])
        ->middleware('throttle:webhook-trigger')
        ->name('events.trigger');
    Route::get('/events/{event}/edit', [DashboardController::class, 'editEvent'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::get('/deliveries', [DashboardController::class, 'deliveries'])->name('deliveries');
});
