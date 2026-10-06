<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can view the event.
     */
    public function view(User $user, Event $event): Response
    {
        return $this->owns($user, $event);
    }

    /**
     * Determine whether the user can update the event.
     */
    public function update(User $user, Event $event): Response
    {
        return $this->owns($user, $event);
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): Response
    {
        return $this->owns($user, $event);
    }

    /**
     * Determine whether the user can sync the event's subscribed endpoints.
     */
    public function syncEndpoints(User $user, Event $event): Response
    {
        return $this->owns($user, $event);
    }

    /**
     * Determine whether the user can trigger the event.
     */
    public function trigger(User $user, Event $event): Response
    {
        return $this->owns($user, $event);
    }

    /**
     * Ownership check shared by every action above. Denies with a 404
     * (rather than the framework's default 403) so that a resource owned
     * by another user is indistinguishable from one that doesn't exist at
     * all — see #102, which fixed the enumeration risk a 403/404 split
     * created here.
     */
    private function owns(User $user, Event $event): Response
    {
        return $user->id === $event->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
