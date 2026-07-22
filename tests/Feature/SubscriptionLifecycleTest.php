<?php

use App\Models\User;
use App\Models\Task;
use App\Jobs\SendTaskReminderJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

uses(RefreshDatabase::class);

test('free user stops receiving reminders after reaching three reminder attempts', function () {
    $user = User::factory()->create([
        'subscribed' => false,
        'reminder_attempts' => 3,
        'reminder_email' => true,
    ]);

    $task = $user->tasks()->create([
        'title' => 'Test Morning Task',
        'is_completed' => false,
        'reminder_time' => '09:00',
    ]);

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($message) use ($user) {
            return str_contains($message, "Reminder skipped: free user {$user->id} has reached limit of 3 reminders");
        });

    $job = new SendTaskReminderJob($task->id);
    app()->call([$job, 'handle']);

    expect($user->fresh()->reminder_attempts)->toBe(3);
});

test('payment verification endpoint grants daily or monthly subscription cleanly', function () {
    $user = User::factory()->create([
        'subscribed' => false,
        'reminder_attempts' => 3,
    ]);

    $response = $this->actingAs($user)->postJson('/api/payment/verify', [
        'reference' => 'DEMO_DAILY_REF_123',
        'plan' => 'daily',
    ]);

    $response->assertStatus(200)
             ->assertJson([
                 'status' => 'success',
                 'user' => [
                     'subscribed' => true,
                     'subscription_plan' => 'daily',
                     'reminder_attempts' => 0,
                 ],
             ]);

    $freshUser = $user->fresh();
    expect($freshUser->subscribed)->toBeTrue();
    expect($freshUser->subscription_plan)->toBe('daily');
    expect($freshUser->subscription_expires_at)->not->toBeNull();
    expect($freshUser->subscription_expires_at->isFuture())->toBeTrue();
});

test('user subscribe endpoint grants subscription and resets attempts', function () {
    $user = User::factory()->create([
        'subscribed' => false,
        'reminder_attempts' => 2,
    ]);

    $response = $this->actingAs($user)->postJson('/api/user/subscribe', [
        'plan' => 'monthly',
    ]);

    $response->assertStatus(200);

    $freshUser = $user->fresh();
    expect($freshUser->subscribed)->toBeTrue();
    expect($freshUser->subscription_plan)->toBe('monthly');
    expect($freshUser->reminder_attempts)->toBe(0);
});

test('expired subscription automatically reverts to free plan when checked or fetched via api', function () {
    $user = User::factory()->create([
        'subscribed' => true,
        'subscription_plan' => 'daily',
        'subscription_expires_at' => Carbon::now()->subHour(), // Expired an hour ago
    ]);

    // Fetch profile through /api/user endpoint
    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertStatus(200);

    $freshUser = $user->fresh();
    expect($freshUser->subscribed)->toBeFalse();
    expect($freshUser->subscription_plan)->toBeNull();
});

test('graceful downgrade preserves user tasks and preferences', function () {
    $user = User::factory()->create([
        'subscribed' => true,
        'subscription_plan' => 'daily',
        'subscription_expires_at' => Carbon::now()->subDay(),
        'preferences' => ['primaryChannel' => 'push', 'quietHours' => ['enabled' => true]],
    ]);

    $tasks = [
        $user->tasks()->create(['title' => 'Task 1', 'reminder_time' => '09:00']),
        $user->tasks()->create(['title' => 'Task 2', 'reminder_time' => '13:00']),
        $user->tasks()->create(['title' => 'Task 3', 'reminder_time' => '18:00']),
    ];

    expect($user->tasks()->count())->toBe(3);

    // Trigger check
    $reverted = $user->checkSubscriptionExpiry();

    expect($reverted)->toBeTrue();
    expect($user->fresh()->subscribed)->toBeFalse();
    expect($user->fresh()->tasks()->count())->toBe(3);
    expect($user->fresh()->preferences['primaryChannel'])->toBe('push');
});

test('paystack webhook grants subscription on charge success event', function () {
    $user = User::factory()->create([
        'email' => 'customer@smartdailyplanner.com',
        'subscribed' => false,
    ]);

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'reference' => 'WEBHOOK_REF_999',
            'amount' => 3000,
            'customer' => ['email' => 'customer@smartdailyplanner.com'],
            'metadata' => [
                'custom_fields' => [
                    ['variable_name' => 'plan', 'value' => 'monthly']
                ]
            ]
        ]
    ];

    // With test env (no PAYSTACK_SECRET_KEY set or dummy), let's set env secret or verify
    config(['app.env' => 'testing']);
    // Since env('PAYSTACK_SECRET_KEY') might be null or unset right now in test env, let's verify behavior
    putenv('PAYSTACK_SECRET_KEY=test_secret_123');
    $_ENV['PAYSTACK_SECRET_KEY'] = 'test_secret_123';

    $jsonPayload = json_encode($payload);
    $signature = hash_hmac('sha512', $jsonPayload, 'test_secret_123');

    $response = $this->withHeaders([
        'x-paystack-signature' => $signature,
        'Accept' => 'application/json',
    ])->postJson('/api/webhooks/paystack', $payload);

    $response->assertStatus(200);

    $freshUser = $user->fresh();
    expect($freshUser->subscribed)->toBeTrue();
    expect($freshUser->subscription_plan)->toBe('monthly');
    expect($freshUser->last_payment_reference)->toBe('WEBHOOK_REF_999');
});
