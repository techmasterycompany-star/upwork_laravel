<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\TechnologyController;
use App\Http\Controllers\Api\Admin\JobApprovalController;
use App\Http\Controllers\Api\Admin\CommentModerationController;
use App\Http\Controllers\Api\Admin\AuditLogController;





/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});


use App\Http\Controllers\Api\Admin\UserManagementController;

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::apiResource('categories', CategoryController::class)->except(['show']);
    Route::apiResource('technologies', TechnologyController::class)->except(['show']);

    Route::get('/jobs/pending', [JobApprovalController::class, 'pending']);
    Route::post('/jobs/{job}/approve', [JobApprovalController::class, 'approve']);
    Route::post('/jobs/{job}/reject', [JobApprovalController::class, 'reject']);

    Route::get('/comments', [CommentModerationController::class, 'index']);
    Route::post('/comments/{comment}/hide', [CommentModerationController::class, 'hide']);
    Route::delete('/comments/{comment}', [CommentModerationController::class, 'destroy']);

    Route::get('/users', [UserManagementController::class, 'index']);
    Route::post('/users/{user}/block', [UserManagementController::class, 'block']);
    Route::post('/users/{user}/unblock', [UserManagementController::class, 'unblock']);
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
    
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    
});