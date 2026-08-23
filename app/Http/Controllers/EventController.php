<?php

namespace App\Http\Controllers;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Event;
use App\Rules\ValidEventSchema;
use App\Rules\WebhookPayloadSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class EventController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('events', 'name')->where('user_id', $request->user()->id),
            ],
            'event_type' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'payload' => 'nullable|array',
            'schema' => ['nullable', 'array', new ValidEventSchema],
        ]);

        $request->user()->events()->create($validated);

        return redirect()->route('events')->with('success', 'Event created.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->user_id !== $request->user()->id, 404);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('events', 'name')->where('user_id', $request->user()->id)->ignore($event->id),
            ],
            'event_type' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'payload' => 'nullable|array',
            'schema' => ['nullable', 'array', new ValidEventSchema],
        ]);

        $event->update($validated);

        return redirect()->route('events')->with('success', 'Event updated.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->user_id !== $request->user()->id, 404);

        $event->delete();

        return redirect()->route('events')->with('success', 'Event deleted.');
    }

    public function syncEndpoints(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->user_id !== $request->user()->id, 404);

        $validated = $request->validate([
            'endpoint_ids' => 'array',
            'endpoint_ids.*' => 'exists:endpoints,id',
        ]);

        $userEndpointIds = $request->user()->endpoints()
            ->whereIn('id', $validated['endpoint_ids'] ?? [])
            ->pluck('id');

        $event->endpoints()->sync($userEndpointIds);

        return redirect()->route('events')->with('success', 'Endpoints updated.');
    }

    public function trigger(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->user_id !== $request->user()->id, 404);

        $validated = $request->validate([
            'payload' => ['required', 'array', new WebhookPayloadSize],
        ]);

        $payload = $validated['payload'];

        if ($event->schema) {
            try {
                $schemaValidator = Validator::make($payload, $event->schema);
                $schemaFails = $schemaValidator->fails();
            } catch (Throwable $e) {
                report($e);

                return redirect()->route('events')
                    ->with('error', 'This event has an invalid schema configuration and cannot currently accept triggers.');
            }

            if ($schemaFails) {
                return back()->withErrors($schemaValidator)->withInput();
            }
        }

        foreach ($event->activeEndpoints()->get() as $endpoint) {
            $delivery = Delivery::create([
                'event_id' => $event->id,
                'endpoint_id' => $endpoint->id,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            $this->dispatchDelivery($delivery);
        }

        return redirect()->route('events')->with('success', 'Event triggered.');
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
        } catch (Throwable $e) {
            report($e);

            $delivery->update([
                'status' => 'failed',
                'response_body' => 'Failed to queue delivery for dispatch: '.$e->getMessage(),
                'next_retry_at' => now(),
            ]);
        }
    }
}
