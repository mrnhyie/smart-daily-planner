<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'reminder_push' => 'required|boolean',
            'primary_channel' => 'required|string|in:email,sms',
        ]);

        $user = $request->user();

        // Map primary_channel to legacy boolean columns for backward compat
        $user->update([
            'reminder_push' => $validated['reminder_push'],
            'primary_channel' => $validated['primary_channel'],
            'reminder_email' => $validated['primary_channel'] === 'email',
            'reminder_sms' => $validated['primary_channel'] === 'sms',
        ]);

        Cache::forget("user_{$user->id}");

        return response()->json($user->fresh());
    }
}
