<?php

namespace App\Http\Controllers;

use App\Models\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EndpointController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $endpoint = $request->user()->endpoints()->create($validated);

        return response()->json(
            array_merge($endpoint->load('events')->toArray(), ['plain_secret' => $endpoint->secret_key]),
            201
        );
    }

    public function update(Request $request, Endpoint $endpoint): RedirectResponse
    {
        abort_if($endpoint->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:2048',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $endpoint->update($validated);

        return redirect()->route('endpoints')->with('success', 'Endpoint updated.');
    }

    public function destroy(Request $request, Endpoint $endpoint): RedirectResponse
    {
        abort_if($endpoint->user_id !== $request->user()->id, 403);

        $endpoint->delete();

        return redirect()->route('endpoints')->with('success', 'Endpoint deleted.');
    }

    public function test(Request $request, Endpoint $endpoint)
    {
        abort_if($endpoint->user_id !== $request->user()->id, 403);

        try {
            $response = Http::timeout(10)->post($endpoint->url, ['test' => true]);

            $testResult = [
                'success' => $response->successful(),
                'response_code' => $response->status(),
                'message' => $response->body(),
            ];
        } catch (\Exception $e) {
            $testResult = [
                'success' => false,
                'response_code' => null,
                'message' => $e->getMessage(),
            ];
        }

        $endpoints = $request->user()->endpoints()->with('events')->paginate(15);

        return Inertia::render('Endpoints/Index', [
            'endpoints' => $endpoints,
            'testResult' => $testResult,
        ]);
    }

    public function regenerateSecret(Request $request, Endpoint $endpoint): JsonResponse
    {
        abort_if($endpoint->user_id !== $request->user()->id, 403);

        $newSecret = Str::random(32);
        $endpoint->update(['secret_key' => $newSecret]);

        return response()->json(
            array_merge($endpoint->fresh()->toArray(), ['plain_secret' => $newSecret])
        );
    }
}
