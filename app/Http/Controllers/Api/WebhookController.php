<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Event;
use App\Rules\WebhookPayloadSize;
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
            'payload' => ['required', 'array', new WebhookPayloadSize()],
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

        if (! $event) {
            return response()->json([
                'message' => 'Event not found',
            ], 404);
        }

        $activeEndpoints = $event->activeEndpoints;

        if ($activeEndpoints->isEmpty()) {
            return response()->json([
                'message' => 'No active endpoints configured for this event',
                'deliveries_created' => 0,
            ]);
        }

        $payload = $request->input('payload');

        if ($event->schema) {
            try {
                $schemaValidator = Validator::make($payload, $event->schema);
                $schemaFails = $schemaValidator->fails();
            } catch (\Throwable $e) {
                report($e);

                return response()->json([
                    'message' => 'This event has an invalid schema configuration and cannot currently accept triggers.',
                ], 500);
            }

            if ($schemaFails) {
                return response()->json([
                    'message' => 'Payload does not match event schema',
                    'errors' => $schemaValidator->errors(),
                ], 422);
            }
        }

        $deliveries = [];

        foreach ($activeEndpoints as $endpoint) {
            $delivery = Delivery::create([
                'event_id' => $event->id,
                'endpoint_id' => $endpoint->id,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            $this->dispatchDelivery($delivery);

            $deliveries[] = $delivery;
        }

        return response()->json([
            'message' => 'Webhook event triggered successfully',
            'event' => $event->name,
            'deliveries_created' => count($deliveries),
            'deliveries' => array_map(fn ($d) => [
                'id' => $d->id,
                'endpoint_id' => $d->endpoint_id,
                'status' => $d->status,
            ], $deliveries),
        ]);
    }

    /**
     * Dispatch the delivery job, guarding against a failure at dispatch time
     * (e.g. the "webhooks" queue connection being briefly unreachable).
     * Without this, an exception here would propagate out of trigger() as an
     * uncaught 500 mid-loop — aborting delivery creation for any endpoints
     * later in the loop — and leave this Delivery stuck in "pending"
     * forever, since scopeReadyForRetry() only matches "failed" rows.
     * Marking it failed with next_retry_at set instead lets the normal
     * webhooks:process-retries cron pick it back up.
     */
    private function dispatchDelivery(Delivery $delivery): void
    {
        try {
            SendWebhook::dispatch($delivery);
        } catch (\Throwable $e) {
            report($e);

            $delivery->update([
                'status' => 'failed',
                'response_body' => 'Failed to queue delivery for dispatch: '.$e->getMessage(),
                'next_retry_at' => now(),
            ]);
        }
    }

    /**
     * Retry a failed delivery
     */
    public function retryDelivery(Request $request, Delivery $delivery): JsonResponse
    {
        // Load the event with trashed included: a soft-deleted parent event
        // must not turn this ownership check into a crash on a null relation.
        $event = $delivery->event()->withTrashed()->first();

        // Ensure the delivery belongs to the authenticated user
        if (! $event || $event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if (! $delivery->isFailed()) {
            return response()->json([
                'message' => 'Only failed deliveries can be retried',
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
