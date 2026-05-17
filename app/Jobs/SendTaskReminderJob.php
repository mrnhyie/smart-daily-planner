<?php

namespace App\Jobs;

use App\Models\Task;
use App\Services\ReminderGateway;
use App\Services\WebPushService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        if (!$task || !$task->user || !$task->title || !$task->user->phone) {
            return;
        }

        $today = Carbon::today()->toDateString();

        if ($this->channel === 'whatsapp') {
            if (!$task->user->reminder_whatsapp || $task->last_whatsapp_reminded_on === $today) {
                return;
            }

            $gateway->sendWhatsapp($task->user->phone, $this->messageForTask($task));
            $task->update(['last_whatsapp_reminded_on' => $today]);
            return;
        }

        if ($this->channel === 'sms') {
            if (!$task->user->reminder_sms || $task->last_sms_reminded_on === $today) {
                return;
            }

            $gateway->sendSms($task->user->phone, $this->messageForTask($task));
            $task->update(['last_sms_reminded_on' => $today]);
            return;
        }

        if ($this->channel === 'push') {
            if (!$task->user->reminder_push || $task->last_push_reminded_on === $today) {
                return;
            }

            $webPushService->sendToUser(
                $task->user,
                'Task Reminder',
                $this->messageForTask($task)
            );

            $task->update(['last_push_reminded_on' => $today]);
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
