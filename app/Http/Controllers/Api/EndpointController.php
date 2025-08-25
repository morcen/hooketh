<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

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

        return response()->json($endpoint->load('events'), 201);
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
