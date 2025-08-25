<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $events = $request->user()->events()
            ->with(['endpoints', 'deliveries' => function ($query) {
                $query->latest()->limit(5);
            }])
            ->paginate(15);

        return response()->json($events);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:events,name',
            'description' => 'nullable|string|max:1000',
            'endpoint_ids' => 'array',
            'endpoint_ids.*' => 'exists:endpoints,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $endpointIds = $data['endpoint_ids'] ?? [];
        unset($data['endpoint_ids']);

        $event = $request->user()->events()->create($data);

        // Attach endpoints that belong to the user
        if (!empty($endpointIds)) {
            $userEndpointIds = $request->user()->endpoints()
                ->whereIn('id', $endpointIds)
                ->pluck('id');
            $event->endpoints()->attach($userEndpointIds);
        }

        return response()->json($event->load('endpoints'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        if ($event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($event->load([
            'endpoints',
            'deliveries' => function ($query) {
                $query->with('endpoint')->latest()->limit(20);
            }
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        if ($event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255|unique:events,name,' . $event->id,
            'description' => 'nullable|string|max:1000',
            'endpoint_ids' => 'array',
            'endpoint_ids.*' => 'exists:endpoints,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $endpointIds = $data['endpoint_ids'] ?? null;
        unset($data['endpoint_ids']);

        $event->update($data);

        // Sync endpoints if provided
        if ($endpointIds !== null) {
            $userEndpointIds = $request->user()->endpoints()
                ->whereIn('id', $endpointIds)
                ->pluck('id');
            $event->endpoints()->sync($userEndpointIds);
        }

        return response()->json($event->load('endpoints'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        if ($event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }
}
