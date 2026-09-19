<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
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
            'total_deliveries' => Delivery::whereHas('event', fn ($q) => $q->where('user_id', $user->id))->count(),
            'successful_deliveries' => Delivery::whereHas('event', fn ($q) => $q->where('user_id', $user->id))->where('status', 'success')->count(),
        ];

        // Get recent deliveries
        $recentDeliveries = Delivery::with(['event', 'endpoint'])
            ->whereHas('event', fn ($q) => $q->where('user_id', $user->id))
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
        $query = $request->user()->endpoints()->with('events');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status') === 'active');
        }

        $endpoints = $query->paginate(15)->withQueryString();

        return Inertia::render('Endpoints/Index', [
            'endpoints' => $endpoints,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function events(Request $request): Response
    {
        $query = $request->user()->events()->with('endpoints');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('event_type', $request->string('type'));
        }

        $events = $query->paginate(15)->withQueryString();

        $eventTypes = $request->user()->events()
            ->whereNotNull('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        return Inertia::render('Events/Index', [
            'events' => $events,
            'endpoints' => $request->user()->endpoints()->get(['id', 'name', 'url', 'description', 'is_active']),
            'eventTypes' => $eventTypes,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function editEvent(Request $request, Event $event): Response
    {
        abort_if($event->user_id !== $request->user()->id, 404);

        $event->load('endpoints');
        $endpoints = $request->user()->endpoints()->get();

        return Inertia::render('Events/Edit', [
            'event' => $event,
            'endpoints' => $endpoints,
        ]);
    }

    public function deliveries(Request $request): Response
    {
        $query = Delivery::with(['event', 'endpoint'])
            ->whereHas('event', fn ($q) => $q->where('user_id', $request->user()->id));

        // Apply filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('endpoint_id') && $request->endpoint_id) {
            $query->where('endpoint_id', $request->endpoint_id);
        }

        $filteredEvent = null;

        if ($request->has('event_id') && $request->event_id) {
            $query->where('event_id', $request->event_id);

            $filteredEvent = $request->user()->events()
                ->find($request->event_id, ['id', 'name']);
        }

        if ($request->has('event_name') && $request->event_name) {
            $query->whereHas('event', fn ($q) => $q->where('name', 'like', '%'.$request->event_name.'%'));
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $statusCounts = (clone $query)
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $deliveryCounts = [
            'successful' => (int) ($statusCounts['success'] ?? 0),
            'failed' => (int) ($statusCounts['failed'] ?? 0),
            'pending' => (int) ($statusCounts['pending'] ?? 0) + (int) ($statusCounts['retrying'] ?? 0),
        ];

        $deliveries = $query->latest()->paginate(20);

        // Get filter options
        $endpoints = $request->user()->endpoints()->get(['id', 'name']);

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'deliveryCounts' => $deliveryCounts,
            'endpoints' => $endpoints,
            'filters' => $request->only(['status', 'endpoint_id', 'event_id', 'event_name', 'from_date', 'to_date']),
            'filteredEvent' => $filteredEvent,
        ]);
    }
}
