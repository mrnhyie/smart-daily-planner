<?php

namespace App\Console\Commands;

use App\Jobs\SendTaskReminderJob;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DispatchDueReminders extends Command
{
    protected $signature = 'reminders:dispatch-due';
    protected $description = 'Dispatch queued reminder jobs for tasks due in the current minute based on advanced preferences';

    public function handle(): int
    {
        $now = Carbon::now();
        $dispatchedCount = 0;

        // Active uncompleted tasks with a title and a reminder time
        $activeTasks = Task::query()
            ->with('user')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereNotNull('reminder_time')
            ->where('is_completed', false)
            ->get();

        foreach ($activeTasks as $task) {
            $user = $task->user;
            if (!$user) {
                continue;
            }

            $preferences = $user->preferences ?? [];
            $timing = $preferences['timing'] ?? ['scheduled' => true];
            $quietHours = $preferences['quietHours'] ?? ['enabled' => false, 'start' => '22:00', 'end' => '07:00'];
            $repeat = $preferences['repeat'] ?? ['enabled' => false, 'maxRepeats' => 3, 'intervalMinutes' => 15];
            
            // Check Quiet Hours
            if ($this->isQuietHours($now, $quietHours)) {
                continue;
            }

            $isDue = false;

            // 1. Initial Schedule Checks
            $reminderTimeStr = substr($task->reminder_time, 0, 5); // "HH:MM"
            $reminderCarbon = Carbon::createFromFormat('H:i', $reminderTimeStr);
            
            // Map settings to offset minutes
            $offsets = [];
            if (!empty($timing['scheduled'])) $offsets[] = 0;
            if (!empty($timing['before10m'])) $offsets[] = 10;
            if (!empty($timing['before30m'])) $offsets[] = 30;
            if (!empty($timing['before1h'])) $offsets[] = 60;

            foreach ($offsets as $offset) {
                $targetTime = $reminderCarbon->copy()->subMinutes($offset)->format('H:i');
                if ($now->format('H:i') === $targetTime) {
                    $isDue = true;
                    break;
                }
            }

            // 2. Repeat Check
            if (!$isDue && !empty($repeat['enabled']) && $task->reminders_sent > 0 && $task->last_reminded_at) {
                $lastReminded = Carbon::parse($task->last_reminded_at);
                $maxRepeats = (int)($repeat['maxRepeats'] ?? 3);
                $interval = (int)($repeat['intervalMinutes'] ?? 15);
                
                if ($task->reminders_sent < $maxRepeats) {
                    $nextRepeatTime = $lastReminded->copy()->addMinutes($interval);
                    if ($now->format('H:i') === $nextRepeatTime->format('H:i') && $now->isSameDay($nextRepeatTime)) {
                        $isDue = true;
                    }
                }
            }

            if ($isDue) {
                SendTaskReminderJob::dispatch($task->id, 'all');
                $dispatchedCount++;
            }
        }

        $this->info("Dispatched {$dispatchedCount} reminder job(s).");

        return self::SUCCESS;
    }

    private function isQuietHours(Carbon $now, array $quietHours): bool
    {
        if (empty($quietHours['enabled'])) {
            return false;
        }

        $startStr = $quietHours['start'] ?? '22:00';
        $endStr = $quietHours['end'] ?? '07:00';

        $start = Carbon::createFromFormat('H:i', $startStr);
        $end = Carbon::createFromFormat('H:i', $endStr);
        $current = Carbon::createFromFormat('H:i', $now->format('H:i'));

        if ($start->greaterThan($end)) {
            // Crosses midnight (e.g. 22:00 to 07:00)
            return $current->greaterThanOrEqualTo($start) || $current->lessThanOrEqualTo($end);
        } else {
            // Same day (e.g. 01:00 to 05:00)
            return $current->between($start, $end);
        }
    }
}
