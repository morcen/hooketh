<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EndpointController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $endpoints = $request->user()->endpoints()
            ->with('events')
            ->paginate(15);

        return response()->json($endpoints);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $endpoint = $request->user()->endpoints()->create($validator->validated());

        $plainSecret = $endpoint->secret_key;

        return response()->json(
            array_merge($endpoint->load('events')->toArray(), ['plain_secret' => $plainSecret]),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Endpoint $endpoint): JsonResponse
    {
        if ($endpoint->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($endpoint->load(['events', 'deliveries' => function ($query) {
            $query->latest()->limit(10);
        }]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Endpoint $endpoint): JsonResponse
    {
        if ($endpoint->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'url' => 'url|max:2048',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $endpoint->update($validator->validated());

        return response()->json($endpoint->load('events'));
    }

    /**
     * Regenerate the secret key for the specified endpoint.
     */
    public function regenerateSecret(Request $request, Endpoint $endpoint): JsonResponse
    {
        if ($endpoint->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $newSecret = Str::random(32);
        $endpoint->update(['secret_key' => $newSecret]);

        return response()->json(
            array_merge($endpoint->fresh()->toArray(), ['plain_secret' => $newSecret])
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Endpoint $endpoint): JsonResponse
    {
        if ($endpoint->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $endpoint->delete();

        return response()->json(['message' => 'Endpoint deleted successfully']);
    }
}
