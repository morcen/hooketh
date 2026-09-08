<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Ensure the current request's Sanctum access token is scoped to the
     * given ability before proceeding.
     *
     * Session-authenticated requests (the Inertia/web UI, and tests using
     * actingAs()) carry Sanctum's TransientToken, whose can() always
     * returns true, so this only restricts requests actually made with a
     * personal access token that was scoped to a limited set of
     * abilities. Without this check, a token restricted to "read" in the
     * API Tokens UI silently retained full create/update/delete access,
     * since `auth:sanctum` alone only verifies the token is valid, not
     * what it's scoped to do.
     */
    protected function authorizeAbility(Request $request, string $ability): void
    {
        abort_unless(
            $request->user()->tokenCan($ability),
            403,
            "This action requires the \"{$ability}\" API token ability."
        );
    }
}
