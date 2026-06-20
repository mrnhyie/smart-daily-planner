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
        public string $channel,
    ) {
    }

    public function handle(ReminderGateway $gateway, WebPushService $webPushService): void
    {
        $task = Task::query()->with('user')->find($this->taskId);

        if (!$task || !$task->user || !$task->title) {
            return;
        }

        $today = Carbon::today()->toDateString();
        $user = $task->user;

        // --- Push notification (always independent, free) ---
        if ($this->channel === 'push') {
            if (!$user->reminder_push || $task->last_push_reminded_on === $today) {
                return;
            }

            try {
                $webPushService->sendToUser(
                    $user,
                    'Task Reminder',
                    $this->messageForTask($task)
                );
                $task->update(['last_push_reminded_on' => $today]);
            } catch (\Throwable $e) {
                Log::error("Push Reminder failed for task {$task->id}: " . $e->getMessage());
            }
            return;
        }

        // --- Primary delivery channel (email or sms) with auto-fallback ---
        if ($this->channel === 'delivery') {
            $primaryChannel = $user->primary_channel ?? 'email';
            $message = $this->messageForTask($task);
            $delivered = false;

            // Try primary channel
            if ($primaryChannel === 'email') {
                $delivered = $this->tryEmail($gateway, $user, $task, $message, $today);
                if (!$delivered) {
                    Log::warning("Primary (email) failed for task {$task->id}, falling back to SMS.");
                    $delivered = $this->trySms($gateway, $user, $task, $message, $today);
                }
            } else {
                $delivered = $this->trySms($gateway, $user, $task, $message, $today);
                if (!$delivered) {
                    Log::warning("Primary (SMS) failed for task {$task->id}, falling back to email.");
                    $delivered = $this->tryEmail($gateway, $user, $task, $message, $today);
                }
            }

            if (!$delivered) {
                Log::error("All delivery channels failed for task {$task->id}.");
            }
        }
    }

    protected function tryEmail(ReminderGateway $gateway, $user, Task $task, string $message, string $today): bool
    {
        if (!$user->email || $task->last_email_reminded_on === $today) {
            return false;
        }

        try {
            $subject = 'Task Reminder – ' . $task->title;
            $gateway->sendEmail($user->email, $subject, $message);
            $task->update(['last_email_reminded_on' => $today]);
            return true;
        } catch (\Throwable $e) {
            Log::error("Email Reminder failed for task {$task->id}: " . $e->getMessage());
            return false;
        }
    }

    protected function trySms(ReminderGateway $gateway, $user, Task $task, string $message, string $today): bool
    {
        if (!$user->phone || $task->last_sms_reminded_on === $today) {
            return false;
        }

        try {
            $gateway->sendSms($user->phone, $message);
            $task->update(['last_sms_reminded_on' => $today]);
            return true;
        } catch (\Throwable $e) {
            Log::error("SMS Reminder failed for task {$task->id}: " . $e->getMessage());
            return false;
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
