<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployerProfileController;
use App\Http\Controllers\Api\JobListingController;


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

Route::middleware(['auth:sanctum', 'role:employer'])->prefix('employer')->group(function () {
    Route::get('/profile', [EmployerProfileController::class, 'show']);
    Route::put('/profile', [EmployerProfileController::class, 'update']);
    Route::post('/profile/logo', [EmployerProfileController::class, 'uploadLogo']);

    Route::get('/jobs', [JobListingController::class, 'index']);
    Route::post('/jobs', [JobListingController::class, 'store']);
    Route::get('/jobs/{job}', [JobListingController::class, 'show']);
    Route::put('/jobs/{job}', [JobListingController::class, 'update']);
    Route::patch('/jobs/{job}/close', [JobListingController::class, 'close']);
});