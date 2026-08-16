<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * Display a listing of deliveries
     */
    public function index(Request $request): JsonResponse
    {
        $query = Delivery::query()
            ->with(['event' => fn ($q) => $q->withTrashed(), 'endpoint'])
            ->whereHas('event', function ($q) use ($request) {
                $q->withTrashed()->where('user_id', $request->user()->id);
            });

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by endpoint
        if ($request->has('endpoint_id')) {
            $query->where('endpoint_id', $request->input('endpoint_id'));
        }

        // Filter by event
        if ($request->has('event_id')) {
            $query->where('event_id', $request->input('event_id'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $deliveries = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($deliveries);
    }

    /**
     * Display the specified delivery
     */
    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        // Load the event with trashed included: a soft-deleted parent event
        // must not make an existing delivery's ownership check (or its
        // history) silently disappear.
        $event = $delivery->event()->withTrashed()->first();

        // Ensure the delivery belongs to the authenticated user
        if (! $event || $event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($delivery->load(['event' => fn ($q) => $q->withTrashed(), 'endpoint']));
    }

    /**
     * Get delivery statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $baseQuery = Delivery::query()
            ->whereHas('event', function ($q) use ($request) {
                $q->withTrashed()->where('user_id', $request->user()->id);
            });

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'successful' => (clone $baseQuery)->where('status', 'success')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'retrying' => (clone $baseQuery)->where('status', 'retrying')->count(),
        ];

        $stats['success_rate'] = $stats['total'] > 0
            ? round(($stats['successful'] / $stats['total']) * 100, 2)
            : 0;

        return response()->json($stats);
    }
}
