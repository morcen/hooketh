<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get statistics
        $stats = [
            'endpoints' => $user->endpoints()->count(),
            'events' => $user->events()->count(),
            'total_deliveries' => Delivery::whereHas('event', fn($q) => $q->where('user_id', $user->id))->count(),
            'successful_deliveries' => Delivery::whereHas('event', fn($q) => $q->where('user_id', $user->id))->where('status', 'success')->count(),
        ];

        // Get recent deliveries
        $recentDeliveries = Delivery::with(['event', 'endpoint'])
            ->whereHas('event', fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentDeliveries' => $recentDeliveries,
        ]);
    }

    public function endpoints(Request $request): Response
    {
        $endpoints = $request->user()->endpoints()
            ->with('events')
            ->paginate(15);

        return Inertia::render('Endpoints/Index', [
            'endpoints' => $endpoints,
        ]);
    }

    public function events(Request $request): Response
    {
        $events = $request->user()->events()
            ->with('endpoints')
            ->paginate(15);

        return Inertia::render('Events/Index', [
            'events' => $events,
        ]);
    }

    public function deliveries(Request $request): Response
    {
        $query = Delivery::with(['event', 'endpoint'])
            ->whereHas('event', fn($q) => $q->where('user_id', $request->user()->id));

        // Apply filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('endpoint_id') && $request->endpoint_id) {
            $query->where('endpoint_id', $request->endpoint_id);
        }

        $deliveries = $query->latest()->paginate(20);

        // Get filter options
        $endpoints = $request->user()->endpoints()->get(['id', 'name']);

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'endpoints' => $endpoints,
            'filters' => $request->only(['status', 'endpoint_id']),
        ]);
    }
}
