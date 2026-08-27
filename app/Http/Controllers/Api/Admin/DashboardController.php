<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\JobListing;
use App\Models\Application;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_employers' => User::where('role', 'employer')->count(),
            'total_candidates' => User::where('role', 'candidate')->count(),
            'blocked_users' => User::where('is_blocked', true)->count(),

            'total_jobs' => JobListing::count(),
            'pending_jobs' => JobListing::where('status', 'pending_approval')->count(),
            'approved_jobs' => JobListing::where('status', 'approved')->count(),
            'rejected_jobs' => JobListing::where('status', 'rejected')->count(),

            'total_applications' => Application::count(),
            'accepted_applications' => Application::where('status', 'accepted')->count(),
            'rejected_applications' => Application::where('status', 'rejected')->count(),
        ];

        $recentActivity = AuditLog::with('user')
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
            ],
        ]);
    }
}