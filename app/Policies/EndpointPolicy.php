<?php

namespace App\Policies;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EndpointPolicy
{
    /**
     * Determine whether the user can view the endpoint.
     */
    public function view(User $user, Endpoint $endpoint): Response
    {
        return $this->owns($user, $endpoint);
    }

    /**
     * Determine whether the user can update the endpoint.
     */
    public function update(User $user, Endpoint $endpoint): Response
    {
        return $this->owns($user, $endpoint);
    }

    /**
     * Determine whether the user can delete the endpoint.
     */
    public function delete(User $user, Endpoint $endpoint): Response
    {
        return $this->owns($user, $endpoint);
    }

    /**
     * Determine whether the user can regenerate the endpoint's secret key.
     */
    public function regenerateSecret(User $user, Endpoint $endpoint): Response
    {
        return $this->owns($user, $endpoint);
    }

    /**
     * Determine whether the user can send a test request to the endpoint.
     */
    public function test(User $user, Endpoint $endpoint): Response
    {
        return $this->owns($user, $endpoint);
    }

    /**
     * Ownership check shared by every action above. Denies with a 404
     * (rather than the framework's default 403) so that a resource owned
     * by another user is indistinguishable from one that doesn't exist at
     * all — see #102, which fixed the enumeration risk a 403/404 split
     * created here.
     */
    private function owns(User $user, Endpoint $endpoint): Response
    {
        return $user->id === $endpoint->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
