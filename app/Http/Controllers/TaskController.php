<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        
        $tasks = Cache::remember("user_{$userId}_tasks", 60, function () {
            return Auth::user()
                ->tasks()
                ->orderBy('task_order')
                ->orderByDesc('created_at')
                ->get();
        });

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reminder_time' => 'nullable|date_format:H:i',
            'task_order' => 'nullable|integer|min:0|max:2',
        ]);

        $task = Auth::user()->tasks()->create($validated);

        return response()->json($task, 201);
    }

    public function show(Task $task)
    {
        $this->authorizeOwnership($task);
        return response()->json($task);
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeOwnership($task);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_completed' => 'boolean',
            'reminder_time' => 'nullable|regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
            'task_order' => 'nullable|integer|min:0|max:2',
        ]);

        $task->update($validated);
        
        Cache::forget("user_" . Auth::id() . "_tasks");
        
        return response()->json($task);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'tasks' => 'required|array',
            'tasks.*.title' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string',
            'tasks.*.is_completed' => 'boolean',
            'tasks.*.reminder_time' => 'nullable|regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
            'tasks.*.task_order' => 'nullable|integer|min:0|max:2',
        ]);

        $user = Auth::user();
        $newTasks = collect($validated['tasks']);

        DB::transaction(function () use ($user, $newTasks) {
            $ordersToKeep = $newTasks->pluck('task_order')->filter(function ($value) {
                return $value !== null;
            })->toArray();
            
            // Delete removed tasks
            $user->tasks()->whereNotIn('task_order', $ordersToKeep)->delete();
            
            // Update or create existing tasks
            foreach ($newTasks as $taskData) {
                $user->tasks()->updateOrCreate(
                    ['task_order' => $taskData['task_order']],
                    $taskData
                );
            }
        });

        Cache::forget("user_{$user->id}_tasks");

        return response()->json(['message' => 'Tasks bulk updated successfully']);
    }

    public function destroy(Task $task)
    {
        $this->authorizeOwnership($task);
        $task->delete();
        return response()->json(null, 204);
    }

    /**
     * Internal helper to keep things DRY
     */
    protected function authorizeOwnership(Task $task)
    {
        if (Auth::id() !== $task->user_id) {
            abort(403, 'Unauthorized action.');
        }
    }
}
