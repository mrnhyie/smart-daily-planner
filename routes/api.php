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

// Temporary route to debug push subscriptions in database
Route::get('/debug-push-subscriptions', function () {
    try {
        $count = \App\Models\PushSubscription::count();
        $subscriptions = \App\Models\PushSubscription::with('user')->get();
        return response()->json([
            'total_subscriptions_count' => $count,
            'vapid_configured' => (bool) config('services.webpush.public_key') && (bool) config('services.webpush.private_key'),
            'public_key_first_10' => substr((string) config('services.webpush.public_key'), 0, 10) . '...',
            'subscriptions' => $subscriptions->map(fn($s) => [
                'id' => $s->id,
                'user_id' => $s->user_id,
                'user_name' => $s->user?->name,
                'user_phone' => $s->user?->phone,
                'endpoint_domain' => parse_url($s->endpoint, PHP_URL_HOST),
                'created_at' => $s->created_at?->toDateTimeString(),
            ]),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Temporary route to purge ALL push subscriptions (use once to clear stale VAPID-key subscriptions)
// Using GET so it works reliably in Vercel's PHP serverless runtime.
Route::get('/debug-push-subscriptions/purge', function (\Illuminate\Http\Request $request) {
    if ($request->query('token') !== 'purge-vapid-2024') {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    try {
        $deleted = \App\Models\PushSubscription::query()->delete();
        return response()->json([
            'status'  => 'success',
            'deleted' => $deleted,
            'message' => 'All push subscriptions purged. Users must re-enable push in the app.',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $userId = $request->user()->id;
        return \Illuminate\Support\Facades\Cache::remember("user_{$userId}", 60, function () use ($request) {
            return $request->user();
        });
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/user/preferences', [UserPreferenceController::class, 'update']);
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);
    
    Route::post('/tasks/bulk-update', [TaskController::class, 'bulkUpdate']);
    Route::apiResource('/tasks', TaskController::class);

    // Instant diagnostic notification test trigger
    Route::post('/user/test-notification', function (Request $request) {
        $user = $request->user();

        $sent = [];
        $errors = [];

        // 1. Instant Push Test
        if ($user->reminder_push) {
            try {
                $webPush = app(\App\Services\WebPushService::class);
                $webPush->sendToUser($user, 'Instant Test! 🎉', 'This is an instant Web Push notification test! Your settings are active.');
                $sent[] = 'push';
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Test Push failed: " . $e->getMessage());
                $errors[] = 'push: ' . $e->getMessage();
            }
        }

        // 2. Instant Email Test (when primary channel is email)
        if ($user->reminder_email) {
            if (!$user->email) {
                $errors[] = 'email: No email address set for this user.';
            } else {
                try {
                    $gateway = app(\App\Services\ReminderGateway::class);
                    $gateway->sendEmail(
                        $user->email,
                        'Smart Daily Planner – Test Reminder ⏰',
                        'This is an instant email notification test! Your email reminders are active. - Smart Daily Planner'
                    );
                    $sent[] = 'email';
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Test Email failed: " . $e->getMessage());
                    $errors[] = 'email: ' . $e->getMessage();
                }
            }
        }

        // 3. Instant SMS Test (when primary channel is sms)
        if ($user->reminder_sms) {
            if (!$user->phone) {
                $errors[] = 'sms: No phone number set for this user.';
            } else {
                try {
                    $gateway = app(\App\Services\ReminderGateway::class);
                    $gateway->sendSms($user->phone, 'This is an instant SMS notification test! Your settings are active. - Smart Daily Planner');
                    $sent[] = 'sms';
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Test SMS failed: " . $e->getMessage());
                    $errors[] = 'sms: ' . $e->getMessage();
                }
            }
        }

        return response()->json([
            'message' => 'Instant test notifications triggered!',
            'sent_channels' => $sent,
            'errors' => $errors,
            'primary_channel' => $user->primary_channel ?? 'email',
        ]);
    });
});
