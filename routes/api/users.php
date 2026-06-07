<?php

use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/users/status', function () {
    return ['status' => 'Users API is running'];
});

Route::middleware(['auth:sanctum', 'role:user'])->prefix('user')->group(function () {});

Route::middleware(['auth:sanctum', 'role:publisher'])->prefix('publisher')->group(function () {});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/status', [UserController::class, 'changeStatus']);
    Route::patch('/users/{id}/role', [UserController::class, 'changeRole']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

});
