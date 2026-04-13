<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::apiResource('/users', 'App\Http\Controllers\UserController');
Route::apiResource('/tasks', 'App\Http\Controllers\TaskController');
