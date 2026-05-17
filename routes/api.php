<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserPreferenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/user/preferences', [UserPreferenceController::class, 'update']);
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    Route::apiResource('/tasks', TaskController::class);
});
