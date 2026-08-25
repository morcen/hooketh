<?php

namespace App\Http\Controllers;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class DeliveryController extends Controller
{
    public function retry(Request $request, Delivery $delivery): RedirectResponse
    {
        // Load the event with trashed included: a soft-deleted parent event
        // must not turn this ownership check into a crash on a null relation.
        $event = $delivery->event()->withTrashed()->first();

        abort_if(! $event || $event->user_id !== $request->user()->id, 404);

        if (! $delivery->isFailed()) {
            return back()->with('error', 'Only failed deliveries can be retried.');
        }

        $delivery->update([
            'status' => 'pending',
            'next_retry_at' => null,
        ]);

        $this->dispatchDelivery($delivery);

        return back()->with('success', 'Delivery retry initiated.');
    }

    public function retryFailed(Request $request): RedirectResponse
    {
        $deliveries = Delivery::failed()
            ->whereHas('event', fn ($q) => $q->where('user_id', $request->user()->id))
            ->get();

        foreach ($deliveries as $delivery) {
            $delivery->update([
                'status' => 'pending',
                'next_retry_at' => null,
            ]);

            $this->dispatchDelivery($delivery);
        }

        $count = $deliveries->count();
        $noun = $count === 1 ? 'delivery' : 'deliveries';

        return back()->with('success', "Retrying {$count} failed {$noun}.");
    }

    /**
     * Dispatch the delivery job, guarding against a failure at dispatch time
     * (e.g. the "webhooks" queue connection being briefly unreachable),
     * mirroring EventController::dispatchDelivery() so a queue outage
     * doesn't leave a retried delivery stuck in "pending" forever.
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
