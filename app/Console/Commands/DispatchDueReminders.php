<?php

namespace App\Console\Commands;

use App\Jobs\SendTaskReminderJob;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DispatchDueReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:dispatch-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch queued reminder jobs for tasks due in the current minute';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $minuteStart = $now->copy()->startOfMinute()->format('H:i:s');
        $minuteEnd = $now->copy()->endOfMinute()->format('H:i:s');
        $today = $now->toDateString();

        $dueTasks = Task::query()
            ->with('user')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereNotNull('reminder_time')
            ->whereTime('reminder_time', '>=', $minuteStart)
            ->whereTime('reminder_time', '<=', $minuteEnd)
            ->get();

        $dispatchedCount = 0;

        foreach ($dueTasks as $task) {
            if (!$task->user || !$task->user->phone) {
                continue;
            }

            if ($task->user->reminder_whatsapp && $task->last_whatsapp_reminded_on !== $today) {
                SendTaskReminderJob::dispatch($task->id, 'whatsapp');
                $dispatchedCount++;
            }

            if ($task->user->reminder_sms && $task->last_sms_reminded_on !== $today) {
                SendTaskReminderJob::dispatch($task->id, 'sms');
                $dispatchedCount++;
            }

            if ($task->user->reminder_push && $task->last_push_reminded_on !== $today) {
                SendTaskReminderJob::dispatch($task->id, 'push');
                $dispatchedCount++;
            }
        }

        $this->info("Dispatched {$dispatchedCount} reminder job(s).");

        return self::SUCCESS;
    }
}
