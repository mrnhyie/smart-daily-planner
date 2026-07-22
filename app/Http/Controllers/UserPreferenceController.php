<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
        ]);

        $user = $request->user();

        $user->update([
            'preferences' => $validated['preferences'],
        ]);

        Cache::forget("user_{$user->id}");

        return response()->json($user->fresh());
    }
}
