<?php

namespace App\Http\Controllers;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Event;
use App\Rules\WebhookPayloadSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'schema' => 'nullable|array',
        ]);

        $request->user()->events()->create($validated);

        return redirect()->route('events')->with('success', 'Event created.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('events', 'name')->where('user_id', $request->user()->id)->ignore($event->id),
            ],
            'event_type' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'payload' => 'nullable|array',
            'schema' => 'nullable|array',
        ]);

        $event->update($validated);

        return redirect()->route('events')->with('success', 'Event updated.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->user_id !== $request->user()->id, 403);

        $event->delete();

        return redirect()->route('events')->with('success', 'Event deleted.');
    }

    public function syncEndpoints(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->user_id !== $request->user()->id, 403);

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
        abort_if($event->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'payload' => ['required', 'array', new WebhookPayloadSize()],
        ]);

        $payload = $validated['payload'];

        if ($event->schema) {
            $request->validate($event->schema);
        }

        foreach ($event->activeEndpoints()->get() as $endpoint) {
            $delivery = Delivery::create([
                'event_id' => $event->id,
                'endpoint_id' => $endpoint->id,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            SendWebhook::dispatch($delivery);
        }

        return redirect()->route('events')->with('success', 'Event triggered.');
    }
}
