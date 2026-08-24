<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployerProfileController;
use App\Http\Controllers\Api\JobListingController;
use App\Http\Controllers\Api\PublicJobListingController;
use App\Http\Controllers\Api\EmployerApplicationController;
use App\Http\Controllers\Api\CandidateSearchController;
use App\Http\Controllers\Api\CandidateProfileController;
use App\Http\Controllers\Api\CandidateSkillController;
use App\Http\Controllers\Api\JobSearchController;
use App\Http\Controllers\Api\SavedSearchController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\CandidateApplicationController;

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

    Route::get('/jobs/{job}/applications', [EmployerApplicationController::class, 'index']);
    Route::get('/applications/{application}', [EmployerApplicationController::class, 'show']);
    Route::patch('/applications/{application}/review', [EmployerApplicationController::class, 'markReviewed']);
    Route::patch('/applications/{application}/accept', [EmployerApplicationController::class, 'accept']);
    Route::patch('/applications/{application}/reject', [EmployerApplicationController::class, 'reject']);

    Route::get('/candidates', [CandidateSearchController::class, 'index']);
    Route::get('/candidates/{candidate}', [CandidateSearchController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:candidate'])->prefix('candidate')->group(function () {
    Route::get('/profile', [CandidateProfileController::class, 'show']);
    Route::put('/profile', [CandidateProfileController::class, 'update']);
    Route::post('/profile/resume', [CandidateProfileController::class, 'uploadResume']);

    Route::get('/skills', [CandidateSkillController::class, 'index']);
    Route::post('/skills', [CandidateSkillController::class, 'store']);
    Route::patch('/skills/{skill}', [CandidateSkillController::class, 'update']);
    Route::delete('/skills/{skill}', [CandidateSkillController::class, 'destroy']);

    Route::get('/saved-searches', [SavedSearchController::class, 'index']);
    Route::post('/saved-searches', [SavedSearchController::class, 'store']);
    Route::delete('/saved-searches/{id}', [SavedSearchController::class, 'destroy']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{job}', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{job}', [WishlistController::class, 'destroy']);
    
    Route::post('/jobs/{job}/apply', [CandidateApplicationController::class, 'store']);
    Route::get('/applications', [CandidateApplicationController::class, 'index']);
    Route::get('/applications/{application}', [CandidateApplicationController::class, 'show']);
    Route::patch('/applications/{application}/cancel', [CandidateApplicationController::class, 'cancel']);
});

Route::prefix('jobs')->group(function () {
    Route::get('/', [PublicJobListingController::class, 'index']);
    Route::get('/search', [JobSearchController::class, 'index']);
    Route::get('/{job}', [PublicJobListingController::class, 'show']);
});