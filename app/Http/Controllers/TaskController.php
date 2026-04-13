<?php

namespace App\Http\Controllers;
namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index()
    {
        return response()->json(Auth::user()->tasks()->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
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
            'is_completed' => 'boolean'
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
