<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DeliveryPolicy
{
    /**
     * Determine whether the user can view the delivery.
     */
    public function view(User $user, Delivery $delivery): Response
    {
        return $this->owns($user, $delivery);
    }

    /**
     * Determine whether the user can retry the delivery.
     */
    public function retry(User $user, Delivery $delivery): Response
    {
        return $this->owns($user, $delivery);
    }

    /**
     * Ownership check shared by every action above. A delivery has no
     * user_id of its own; ownership is derived from its parent event,
     * loaded with trashed included so a soft-deleted event doesn't turn
     * this into a crash on a null relation (see #82) or, worse, silently
     * allow the check to pass. Denies with a 404 (rather than the
     * framework's default 403) so a resource owned by another user is
     * indistinguishable from one that doesn't exist at all — see #102,
     * which fixed the enumeration risk a 403/404 split created here.
     */
    private function owns(User $user, Delivery $delivery): Response
    {
        $event = $delivery->event()->withTrashed()->first();

        return $event && $user->id === $event->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
