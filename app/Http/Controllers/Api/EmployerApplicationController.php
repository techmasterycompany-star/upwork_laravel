<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectApplicationRequest;
use App\Models\Application;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployerApplicationController extends Controller
{
    public function index(Request $request, JobListing $job): JsonResponse
    {
        if ($job->employer_id !== $request->user()->employerProfile?->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view applications for this job.',
            ], 403);
        }

        $applications = $job->applications()
            ->with('candidate.user', 'candidate.skills')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'applications' => $applications,
        ]);
    }

  
    public function show(Request $request, Application $application): JsonResponse
    {
        $application->load('job', 'candidate.user', 'candidate.skills');

        if ($application->job->employer_id !== $request->user()->employerProfile?->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this application.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'application' => $application,
        ]);
    }

   
    public function markReviewed(Request $request, Application $application): JsonResponse
    {
        $this->authorizeOwnership($request, $application);

        $application->update([
            'status' => 'under_review',
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application marked as under review.',
            'application' => $application,
        ]);
    }

    
    public function accept(Request $request, Application $application): JsonResponse
    {
        $this->authorizeOwnership($request, $application);

        $application->update([
            'status' => 'accepted',
            'reviewed_at' => $application->reviewed_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application accepted.',
            'application' => $application,
        ]);
    }

    public function reject(RejectApplicationRequest $request, Application $application): JsonResponse
    {
        $this->authorizeOwnership($request, $application);

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('rejection_reason'),
            'reviewed_at' => $application->reviewed_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application rejected.',
            'application' => $application,
        ]);
    }

    protected function authorizeOwnership(Request $request, Application $application): void
    {
        abort_unless(
            $application->job->employer_id === $request->user()->employerProfile?->id,
            403,
            'You are not authorized to manage this application.'
        );
    }
}