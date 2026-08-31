<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
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
    $redisAvailable = true;
    try {
        Redis::ping();
        $health['services']['redis'] = 'connected';
    } catch (Exception $e) {
        $redisAvailable = false;
        $health['status'] = 'error';
        $health['services']['redis'] = 'disconnected';
    }

    // Check PHP extensions required by the actually configured database
    // and Redis drivers, rather than a fixed Postgres/phpredis list — the
    // project's own documented default (SQLite for tests/local dev, or a
    // predis-based Redis client) needs neither the pgsql/pdo_pgsql nor the
    // redis extension, so requiring them unconditionally made this health
    // check report "error" forever under that default config regardless
    // of whether the app was actually functioning (see #170).
    $requiredExtensions = array_values(array_filter([
        config('database.default') === 'pgsql' ? 'pgsql' : null,
        match (config('database.default')) {
            'pgsql' => 'pdo_pgsql',
            'mysql' => 'pdo_mysql',
            'sqlite' => 'pdo_sqlite',
            default => null,
        },
        config('database.redis.client') === 'phpredis' ? 'redis' : null,
    ]));
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

    $health['extensions'] = collect($requiredExtensions)
        ->mapWithKeys(fn (string $extension) => [$extension => extension_loaded($extension)])
        ->all();

    // Check queue worker via scheduler heartbeat. Skip this entirely once
    // Redis is already known to be unreachable — attempting another Redis
    // call here would just throw again (uncaught, since this closure has
    // no surrounding try/catch), turning the intended graceful 503 into a
    // raw 500 during exactly the outage this endpoint exists to report.
    if ($redisAvailable) {
        try {
            $heartbeat = Redis::get('queue:heartbeat');
            $queueStatus = match (true) {
                $heartbeat === null => 'unknown',
                (now()->timestamp - (int) $heartbeat) <= 120 => 'ok',
                default => 'stale',
            };
        } catch (Exception $e) {
            $queueStatus = 'unknown';
        }
    } else {
        $queueStatus = 'unknown';
    }

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
    Route::post('/deliveries/retry-failed', [DeliveryController::class, 'retryFailed'])
        ->middleware('throttle:webhook-trigger')
        ->name('deliveries.retry-failed');
    Route::post('/deliveries/{delivery}/retry', [DeliveryController::class, 'retry'])
        ->middleware('throttle:webhook-trigger')
        ->name('deliveries.retry');
});
