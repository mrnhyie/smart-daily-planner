<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index()
    {
        return response()->json(
            Auth::user()
                ->tasks()
                ->orderBy('task_order')
                ->orderByDesc('created_at')
                ->get()
        );
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
            'reminder_time' => 'nullable|date_format:H:i',
            'task_order' => 'nullable|integer|min:0|max:2',
        ]);

        $task->update($validated);
        return response()->json($task);
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
