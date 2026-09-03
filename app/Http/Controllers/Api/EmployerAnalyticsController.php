<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployerAnalyticsController extends Controller
{
    /**
     * Issue #45: view/application counts per job, plus overall totals for the employer.
     */
    public function index(Request $request): JsonResponse
    {
        $employerProfile = $request->user()->employerProfile;

        if (! $employerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found.',
            ], 404);
        }

        $jobs = $employerProfile->jobListings()
            ->select('id', 'title', 'status', 'views_count', 'applications_count', 'created_at')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'totals' => [
                    'jobs_count'         => $jobs->count(),
                    'total_views'        => $jobs->sum('views_count'),
                    'total_applications' => $jobs->sum('applications_count'),
                ],
                'jobs' => $jobs,
            ],
        ]);
    }
}