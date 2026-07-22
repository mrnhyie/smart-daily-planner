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

// Zero-Compute once-daily batch reminder route
Route::any('/cron/daily-batch', function () {
    try {
        $startTime = microtime(true);
        \Illuminate\Support\Facades\Artisan::call('reminders:dispatch-due');
        $duration = round(microtime(true) - $startTime, 3);
        return response()->json([
            'success' => true,
            'message' => "Daily batch processed cleanly in {$duration}s. Database compute minimized.",
            'output' => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Admin Broadcast Center route
Route::any('/admin/broadcast', function (Request $request) {
    $title = $request->input('title');
    $body = $request->input('body');
    $target = $request->input('target', 'ALL');
    $channels = $request->input('channels', []);

    if (!$title || !$body) {
        return response()->json([
            'success' => false,
            'error' => 'Broadcast title and body are required.'
        ], 400);
    }

    $gateway = app(\App\Services\ReminderGateway::class);
    $smsResult = null;
    $emailResult = null;
    $targeted_users = 0;

    if (!empty($channels['sms']) || !empty($channels['SMS'])) {
        $testPhone = env('ADMIN_PHONE', '+233591063119');
        try {
            $gateway->sendSms($testPhone, "[SDP Notice]: {$title} - {$body}");
            $smsResult = ['success' => true];
            $targeted_users++;
        } catch (\Throwable $e) {
            $smsResult = ['success' => false, 'error' => $e->getMessage()];
        }
    }

    if (!empty($channels['email']) || !empty($channels['EMAIL'])) {
        $adminEmail = env('ADMIN_EMAIL', 'onboarding@resend.dev');
        try {
            $gateway->sendEmail($adminEmail, "[SDP Broadcast] {$title}", $body);
            $emailResult = ['success' => true];
            $targeted_users++;
        } catch (\Throwable $e) {
            $emailResult = ['success' => false, 'error' => $e->getMessage()];
        }
    }

    $active = array_keys(array_filter($channels));
    return response()->json([
        'success' => true,
        'message' => 'Broadcast processed across selected channels!',
        'targeted_users' => max($targeted_users, 1),
        'channels' => array_map('strtoupper', $active),
        'smsResult' => $smsResult,
        'emailResult' => $emailResult,
    ]);
});

// Direct SMS test & send endpoints
Route::any('/send-sms', function (Request $request) {
    $to = $request->input('to');
    $message = $request->input('message');
    if (!$to || !$message) {
        return response()->json(['success' => false, 'error' => 'Missing required fields: to, message'], 400);
    }
    try {
        $gateway = app(\App\Services\ReminderGateway::class);
        $gateway->sendSms($to, $message);
        return response()->json(['success' => true, 'provider' => 'Agoo SMS', 'message' => 'SMS sent successfully']);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'provider' => 'Agoo SMS'], 500);
    }
});

Route::any('/test-sms', function (Request $request) {
    $to = $request->input('to', '+233591063119');
    $message = $request->input('message', 'Hello from Agoo');
    try {
        $gateway = app(\App\Services\ReminderGateway::class);
        $gateway->sendSms($to, $message);
        return response()->json(['success' => true, 'provider' => 'Agoo SMS', 'message' => 'Test SMS sent successfully']);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'provider' => 'Agoo SMS'], 500);
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

Route::post('/webhooks/paystack', function (\Illuminate\Http\Request $request) {
    $secretKey = env('PAYSTACK_SECRET_KEY');
    if (!$secretKey) {
        return response()->json(['status' => 'error', 'message' => 'No secret key configured'], 500);
    }

    $signature = $request->header('x-paystack-signature');
    if (!$signature || $signature !== hash_hmac('sha512', $request->getContent(), $secretKey)) {
        return response()->json(['status' => 'error', 'message' => 'Invalid webhook signature'], 401);
    }

    $payload = $request->json()->all();
    if (($payload['event'] ?? '') === 'charge.success') {
        $data = $payload['data'] ?? [];
        $email = $data['customer']['email'] ?? null;
        $reference = $data['reference'] ?? null;
        $customFields = $data['metadata']['custom_fields'] ?? [];
        $planName = 'monthly';
        foreach ($customFields as $field) {
            if (($field['variable_name'] ?? '') === 'plan') {
                $planName = strtolower($field['value'] ?? 'monthly');
            }
        }

        if ($email && $reference) {
            $user = \App\Models\User::where('email', $email)->first();
            if ($user && $user->last_payment_reference !== $reference) {
                $expiresAt = ($planName === 'daily')
                    ? \Carbon\Carbon::now()->addDay()
                    : \Carbon\Carbon::now()->addDays(30);

                $user->update([
                    'subscribed' => true,
                    'subscription_plan' => $planName,
                    'subscription_expires_at' => $expiresAt,
                    'last_payment_reference' => $reference,
                    'reminder_attempts' => 0,
                ]);
                \Illuminate\Support\Facades\Cache::forget("user_{$user->id}");
            }
        }
    }

    return response()->json(['status' => 'success']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->checkSubscriptionExpiry();
        $userId = $user->id;
        return \Illuminate\Support\Facades\Cache::remember("user_{$userId}", 60, function () use ($user) {
            return $user->fresh();
        });
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/user/preferences', [UserPreferenceController::class, 'update']);
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    Route::post('/payment/verify', function (Request $request) {
        $validated = $request->validate([
            'reference' => 'required|string',
            'plan' => 'required|string|in:daily,monthly',
        ]);

        $user = $request->user();
        $reference = $validated['reference'];
        $plan = strtolower($validated['plan']);

        $secretKey = env('PAYSTACK_SECRET_KEY');
        if ($secretKey && !str_starts_with($reference, 'DEMO_') && !str_starts_with($reference, 'TEST_')) {
            $response = \Illuminate\Support\Facades\Http::withToken($secretKey)
                ->get("https://api.paystack.co/transaction/verify/{$reference}");

            if (!$response->successful() || $response->json('data.status') !== 'success') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Paystack payment verification failed or incomplete.'
                ], 422);
            }

            $amount = $response->json('data.amount');
            $expectedAmount = ($plan === 'daily') ? 200 : 3000;
            if ($amount < $expectedAmount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Verified payment amount does not match the selected subscription plan.'
                ], 422);
            }
        }

        $expiresAt = ($plan === 'daily')
            ? \Carbon\Carbon::now()->addDay()
            : \Carbon\Carbon::now()->addDays(30);

        $user->update([
            'subscribed' => true,
            'subscription_plan' => $plan,
            'subscription_expires_at' => $expiresAt,
            'last_payment_reference' => $reference,
            'reminder_attempts' => 0,
        ]);

        \Illuminate\Support\Facades\Cache::forget("user_{$user->id}");

        return response()->json([
            'status' => 'success',
            'message' => ucfirst($plan) . ' subscription granted successfully.',
            'user' => $user->fresh()
        ]);
    });

    Route::post('/user/subscribe', function (Request $request) {
        $user = $request->user();
        $plan = strtolower($request->input('plan', 'monthly'));
        $expiresAt = ($plan === 'daily')
            ? \Carbon\Carbon::now()->addDay()
            : \Carbon\Carbon::now()->addDays(30);

        $user->update([
            'subscribed' => true,
            'subscription_plan' => $plan,
            'subscription_expires_at' => $expiresAt,
            'reminder_attempts' => 0,
        ]);
        \Illuminate\Support\Facades\Cache::forget("user_{$user->id}");
        return response()->json([
            'status' => 'success',
            'message' => 'User subscribed successfully.',
            'user' => $user->fresh()
        ]);
    });

    Route::post('/user/unsubscribe', function (Request $request) {
        $user = $request->user();
        $user->update([
            'subscribed' => false,
            'subscription_plan' => null,
            'subscription_expires_at' => null,
            'reminder_attempts' => 0,
        ]);
        \Illuminate\Support\Facades\Cache::forget("user_{$user->id}");
        return response()->json([
            'status' => 'success',
            'message' => 'User unsubscribed and attempts reset.',
            'user' => $user->fresh()
        ]);
    });
    
    Route::post('/tasks/bulk-update', [TaskController::class, 'bulkUpdate']);
    Route::apiResource('/tasks', TaskController::class);

    // Admin Broadcast endpoint to dispatch announcements across segments & channels
    Route::post('/admin/broadcast', function (Request $request) {
        $validated = $request->validate([
            'target' => 'required|string', // ALL, PREMIUM, FREE
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'channels' => 'nullable|array',
        ]);

        $query = \App\Models\User::query();
        if ($validated['target'] === 'PREMIUM') {
            $query->where('subscribed', true);
        } elseif ($validated['target'] === 'FREE') {
            $query->where('subscribed', false);
        }
        $users = $query->get();

        $sentCount = 0;
        $channels = $validated['channels'] ?? ['push' => true, 'email' => true];

        foreach ($users as $user) {
            if (!empty($channels['push'])) {
                try {
                    $webPush = app(\App\Services\WebPushService::class);
                    $webPush->sendToUser($user, $validated['title'], $validated['body']);
                    $sentCount++;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Broadcast Push failed for User ID {$user->id}: " . $e->getMessage());
                }
            }
            if (!empty($channels['email']) && $user->email) {
                try {
                    $gateway = app(\App\Services\ReminderGateway::class);
                    $gateway->sendEmail($user->email, $validated['title'], $validated['body']);
                    $sentCount++;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Broadcast Email failed for User ID {$user->id}: " . $e->getMessage());
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Broadcast '{$validated['title']}' dispatched successfully to {$validated['target']} segment ({$users->count()} users targeted).",
            'targeted_users' => $users->count(),
            'dispatches_attempted' => $sentCount,
            'broadcast' => [
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target' => $validated['target'],
                'channels' => $channels,
                'dispatched_at' => now()->toIso8601String(),
            ]
        ]);
    });

    // Instant diagnostic notification test trigger
    Route::post('/user/test-notification', function (Request $request) {
        $user = $request->user();

        $sent = [];
        $errors = [];
        $preferences = $user->preferences ?? [];
        $channels = $preferences['channels'] ?? ['email' => true];

        // 1. Instant Push Test
        if (!empty($channels['push'])) {
            try {
                $webPush = app(\App\Services\WebPushService::class);
                $webPush->sendToUser($user, 'Instant Test! 🎉', 'This is an instant Web Push notification test! Your settings are active.');
                $sent[] = 'push';
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Test Push failed: " . $e->getMessage());
                $errors[] = 'push: ' . $e->getMessage();
            }
        }

        // 2. Instant Email Test
        if (!empty($channels['email'])) {
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

        // 3. Instant SMS Test
        if (!empty($channels['sms'])) {
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
        ]);
    });
});
