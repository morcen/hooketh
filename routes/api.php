<?php

use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\EndpointController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Protected API routes
Route::middleware('auth:sanctum')->group(function () {
    // Endpoints management
    Route::apiResource('endpoints', EndpointController::class);
    
    // Events management
    Route::apiResource('events', EventController::class);
    
    // Deliveries (logs)
    Route::get('deliveries', [DeliveryController::class, 'index']);
    Route::get('deliveries/stats', [DeliveryController::class, 'stats']);
    Route::get('deliveries/{delivery}', [DeliveryController::class, 'show']);
    
    // Webhook triggering
    Route::post('webhooks/trigger/{eventName}', [WebhookController::class, 'trigger']);
    Route::post('deliveries/{delivery}/retry', [WebhookController::class, 'retryDelivery']);
});
