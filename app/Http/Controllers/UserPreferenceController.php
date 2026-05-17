<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'reminder_whatsapp' => 'required|boolean',
            'reminder_sms' => 'required|boolean',
            'reminder_push' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json($user->fresh());
    }
}
