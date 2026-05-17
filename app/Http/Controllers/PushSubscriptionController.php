<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:2048',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'contentEncoding' => 'nullable|string|max:50',
        ]);

        $user = $request->user();

        $subscription = PushSubscription::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $validated['endpoint'],
            ],
            [
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aesgcm',
                'user_agent' => (string) $request->userAgent(),
            ]
        );

        return response()->json([
            'id' => $subscription->id,
            'endpoint' => $subscription->endpoint,
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|url|max:2048',
        ]);

        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json(['message' => 'Push subscription removed.']);
    }
}
