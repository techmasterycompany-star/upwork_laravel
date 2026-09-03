<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\TechnologyController;
use App\Http\Controllers\Api\Admin\JobApprovalController;
use App\Http\Controllers\Api\Admin\CommentModerationController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\EmployerProfileController;
use App\Http\Controllers\Api\Admin\UserManagementController;
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
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Admin\PlanController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\EmployerSubscriptionController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\PaypalPaymentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\EmployerAnalyticsController;


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
    Route::get('/linkedin/redirect', [AuthController::class, 'redirectToLinkedIn']);
    Route::get('/linkedin/callback', [AuthController::class, 'handleLinkedInCallback']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
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
    
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::apiResource('plans', PlanController::class)->except(['show']);

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
    Route::get('/plans', [EmployerSubscriptionController::class, 'availablePlans']);
    Route::get('/subscription', [EmployerSubscriptionController::class, 'current']);
    Route::post('/subscription', [EmployerSubscriptionController::class, 'subscribe']);
    Route::post('/subscription/cancel', [EmployerSubscriptionController::class, 'cancel']);
    Route::post('/subscription/checkout', [PaymentController::class, 'createCheckoutSession']);
    Route::post('/subscription/paypal/checkout', [PaypalPaymentController::class, 'createOrder']);
    Route::post('/subscription/paypal/capture', [PaypalPaymentController::class, 'captureOrder']);
    Route::get('/payments/history', [PaymentController::class, 'history']);
    Route::post('/jobs/generate-description', [AiController::class, 'generateJobDescription']);
    Route::get('/analytics', [EmployerAnalyticsController::class, 'index']);
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
    Route::post('/jobs/{job}/generate-cover-letter', [AiController::class, 'generateCoverLetter']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/jobs/{job}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    Route::post('/comments/{comment}/report', [CommentController::class, 'report']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/{notification}/unread', [NotificationController::class, 'markAsUnread']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::post('/chatbot/ask', [ChatbotController::class, 'ask']);
});
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
Route::prefix('jobs')->group(function () {
    Route::get('/', [PublicJobListingController::class, 'index']);
    Route::get('/search', [JobSearchController::class, 'index']);
    Route::get('/{job}', [PublicJobListingController::class, 'show']);
});