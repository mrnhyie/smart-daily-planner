<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserPreferenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public cron trigger route
Route::get('/cron/reminders', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('reminders:dispatch-due');
        return response()->json([
            'status' => 'success',
            'output' => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ], 200);
    }
});

// Temporary route to run migrations in production on Vercel
Route::get('/run-migrations', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'output' => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ], 500);
    }
});

// Temporary diagnostic route – REMOVE after debugging
Route::get('/debug-sms-config', function () {
    $apiKey = env('AGOO_SMS_API_KEY', '(NOT SET)');
    $senderId = env('AGOO_SMS_SENDER_ID', '(NOT SET - using default SDPLANNER)');
    return response()->json([
        'sender_id' => $senderId,
        'api_key_first_8' => substr($apiKey, 0, 8) . '...',
        'api_key_length' => strlen($apiKey),
        'all_agoo_env_keys' => collect($_ENV)->filter(fn($v, $k) => str_contains(strtolower($k), 'agoo'))->keys(),
    ]);
});

// Dynamic SMS test endpoint to diagnose Agoo API responses with different sender IDs
Route::get('/test-sms-gateway', function (\Illuminate\Http\Request $request) {
    $apiKey = env('AGOO_SMS_API_KEY');
    $senderId = $request->query('sender_id', env('AGOO_SMS_SENDER_ID', 'SDPLANNER'));
    $to = $request->query('to', '+233591063119');
    $message = $request->query('message', 'Test message from Daily Planner gateway.');

    if (empty($apiKey)) {
        return response()->json(['error' => 'AGOO_SMS_API_KEY is not set.'], 400);
    }

    try {
        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.agoosms.com/v1/sms/send', [
                'to' => $to,
                'message' => $message,
                'sender_id' => $senderId,
            ]);

        return response()->json([
            'sender_id_used' => $senderId,
            'api_status' => $response->status(),
            'api_response' => $response->json(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'sender_id_used' => $senderId,
        ], 500);
    }
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/user/preferences', [UserPreferenceController::class, 'update']);
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);
    Route::apiResource('/tasks', TaskController::class);

    // Instant diagnostic notification test trigger
    Route::post('/user/test-notification', function (Request $request) {
        $user = $request->user();
        if (!$user || !$user->phone) {
            return response()->json(['error' => 'User does not have a phone number configured.'], 400);
        }

        $sent = [];

        // 1. Instant Push Test
        if ($user->reminder_push) {
            try {
                $webPush = app(\App\Services\WebPushService::class);
                $webPush->sendToUser($user, 'Instant Test! 🎉', 'This is an instant Web Push notification test! Your settings are active.');
                $sent[] = 'push';
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Test Push failed: " . $e->getMessage());
            }
        }

        // 2. Instant SMS Test
        if ($user->reminder_sms) {
            try {
                $gateway = app(\App\Services\ReminderGateway::class);
                $gateway->sendSms($user->phone, 'This is an instant SMS notification test! Your settings are active. - Daily Planner');
                $sent[] = 'sms';
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Test SMS failed: " . $e->getMessage());
            }
        }

        // 3. Instant WhatsApp Test
        if ($user->reminder_whatsapp) {
            try {
                $gateway = app(\App\Services\ReminderGateway::class);
                $gateway->sendWhatsapp($user->phone, 'This is an instant WhatsApp notification test! Your settings are active. - Daily Planner');
                $sent[] = 'whatsapp';
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Test WhatsApp failed: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Instant test notifications triggered!',
            'sent_channels' => $sent,
            'phone' => $user->phone
        ]);
    });
});
