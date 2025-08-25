<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    /**
     * Trigger a webhook event
     */
    public function trigger(Request $request, string $eventName): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payload' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $event = Event::where('name', $eventName)
            ->where('user_id', $request->user()->id)
            ->with('activeEndpoints')
            ->first();

        if (!$event) {
            return response()->json([
                'message' => 'Event not found'
            ], 404);
        }

        $activeEndpoints = $event->activeEndpoints;

        if ($activeEndpoints->isEmpty()) {
            return response()->json([
                'message' => 'No active endpoints configured for this event',
                'deliveries_created' => 0,
            ]);
        }

        $deliveries = [];
        $payload = $request->input('payload');

        foreach ($activeEndpoints as $endpoint) {
            $delivery = Delivery::create([
                'event_id' => $event->id,
                'endpoint_id' => $endpoint->id,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            // Dispatch the webhook job
            SendWebhook::dispatch($delivery);

            $deliveries[] = $delivery;
        }

        return response()->json([
            'message' => 'Webhook event triggered successfully',
            'event' => $event->name,
            'deliveries_created' => count($deliveries),
            'deliveries' => $deliveries->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'endpoint_id' => $delivery->endpoint_id,
                    'status' => $delivery->status,
                ];
            }),
        ]);
    }

    /**
     * Retry a failed delivery
     */
    public function retryDelivery(Request $request, Delivery $delivery): JsonResponse
    {
        // Ensure the delivery belongs to the authenticated user
        if ($delivery->event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$delivery->isFailed()) {
            return response()->json([
                'message' => 'Only failed deliveries can be retried'
            ], 400);
        }

        // Reset the delivery status and retry
        $delivery->update([
            'status' => 'pending',
            'next_retry_at' => null,
        ]);

        SendWebhook::dispatch($delivery);

        return response()->json([
            'message' => 'Delivery retry initiated',
            'delivery_id' => $delivery->id,
        ]);
    }
}
