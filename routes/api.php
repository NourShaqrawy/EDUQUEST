<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);
Route::post('logout', [UserController::class, 'logout'])->middleware('auth:sanctum');


Route::post('/courses', [CourseController::class, 'store']);

Route::get('/courses', [CourseController::class, 'index']);
Route::post('/videos', [VideoController::class, 'store']);
Route::post('/certificates', [CertificateController::class, 'issueCertificate']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::get('/courses/{course_id}/videos', [VideoController::class, 'index']);
