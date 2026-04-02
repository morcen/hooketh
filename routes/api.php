<?php

/*
 * API VERSIONING — MIGRATION GUIDE
 *
 * All API routes are now available under /api/v1/...
 * Example: POST /api/v1/webhooks/trigger/{eventName}
 *
 * The unversioned routes (/api/...) remain active as deprecated aliases and will
 * be removed in a future release. Update your API clients to use /api/v1/ paths.
 */

use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\EndpointController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// -------------------------------------------------------------------------
// V1 — canonical versioned routes
// -------------------------------------------------------------------------
Route::prefix('v1')
    ->name('v1.')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::apiResource('endpoints', EndpointController::class);
        Route::post('endpoints/{endpoint}/regenerate-secret', [EndpointController::class, 'regenerateSecret'])
            ->name('endpoints.regenerate-secret');

        Route::apiResource('events', EventController::class);

        Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/stats', [DeliveryController::class, 'stats'])->name('deliveries.stats');
        Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');

        Route::post('webhooks/trigger/{eventName}', [WebhookController::class, 'trigger'])
            ->middleware('throttle:webhook-trigger')
            ->name('webhooks.trigger');
        Route::post('deliveries/{delivery}/retry', [WebhookController::class, 'retryDelivery'])
            ->name('deliveries.retry');
    });

// -------------------------------------------------------------------------
// Unversioned aliases — DEPRECATED, preserved for backwards compatibility
// -------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('endpoints', EndpointController::class);
    Route::post('endpoints/{endpoint}/regenerate-secret', [EndpointController::class, 'regenerateSecret'])
        ->name('endpoints.regenerate-secret');

    Route::apiResource('events', EventController::class);

    Route::get('deliveries', [DeliveryController::class, 'index']);
    Route::get('deliveries/stats', [DeliveryController::class, 'stats']);
    Route::get('deliveries/{delivery}', [DeliveryController::class, 'show']);

    Route::post('webhooks/trigger/{eventName}', [WebhookController::class, 'trigger'])
        ->middleware('throttle:webhook-trigger');
    Route::post('deliveries/{delivery}/retry', [WebhookController::class, 'retryDelivery']);
});
