<?php

namespace App\Jobs;

use App\Models\Task;
use App\Services\ReminderGateway;
use App\Services\WebPushService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTaskReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $taskId,
        public string $channel = 'all' // kept for backwards compat but not strictly needed now
    ) {
    }

    public function handle(ReminderGateway $gateway, WebPushService $webPushService): void
    {
        $task = Task::query()->with('user')->find($this->taskId);

        if (!$task || !$task->user || !$task->title || $task->is_completed) {
            return;
        }

        $user = $task->user;
        $user->checkSubscriptionExpiry();
        $preferences = $user->preferences ?? [];
        $channels = $preferences['channels'] ?? ['email' => true];

        // Enforce free tier limit
        if (!$user->subscribed && $user->reminder_attempts >= 3) {
            Log::info("Reminder skipped: free user {$user->id} has reached limit of 3 reminders.");
            return;
        }

        $message = $this->messageForTask($task);
        $sentAny = false;

        // 1. Push
        if (!empty($channels['push'])) {
            try {
                $webPushService->sendToUser($user, 'Task Reminder', $message);
                $sentAny = true;
            } catch (\Throwable $e) {
                Log::error("Push Reminder failed for task {$task->id}: " . $e->getMessage());
            }
        }

        // 2. Email
        if (!empty($channels['email']) && $user->email) {
            try {
                $subject = 'Task Reminder – ' . $task->title;
                $gateway->sendEmail($user->email, $subject, $message);
                $sentAny = true;
            } catch (\Throwable $e) {
                Log::error("Email Reminder failed for task {$task->id}: " . $e->getMessage());
            }
        }

        // 3. SMS
        if (!empty($channels['sms']) && $user->phone) {
            try {
                $gateway->sendSms($user->phone, $message);
                $sentAny = true;
            } catch (\Throwable $e) {
                Log::error("SMS Reminder failed for task {$task->id}: " . $e->getMessage());
            }
        }

        if ($sentAny) {
            $task->update([
                'last_reminded_at' => Carbon::now(),
                'reminders_sent' => $task->reminders_sent + 1,
            ]);

            if (!$user->subscribed) {
                $user->increment('reminder_attempts');
                \Illuminate\Support\Facades\Cache::forget("user_{$user->id}");
            }
        } else {
            Log::warning("No reminder channels successfully delivered for task {$task->id}.");
        }
    }

    protected function messageForTask(Task $task): string
    {
        $label = match ((int) $task->task_order) {
            0 => 'first',
            1 => 'second',
            2 => 'third',
            default => 'next',
        };

        return "Reminder for your {$label} task: {$task->title}";
    }
}
