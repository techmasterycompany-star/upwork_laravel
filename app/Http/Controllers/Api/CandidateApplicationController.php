<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateApplicationController extends Controller
{
   
    public function index(Request $request): JsonResponse
    {
        $applications = $request->user()->candidateProfile
            ->applications()
            ->with('job.category', 'job.employer')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'applications' => $applications,
        ]);
    }

    public function show(Request $request, Application $application): JsonResponse
    {
        $this->authorizeOwnership($request, $application);

        return response()->json([
            'success' => true,
            'application' => $application->load('job.category', 'job.employer'),
        ]);
    }

    public function cancel(Request $request, Application $application): JsonResponse
    {
        $this->authorizeOwnership($request, $application);

        if (! in_array($application->status, ['submitted', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending applications can be cancelled.',
            ], 422);
        }

        $application->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Application cancelled successfully.',
            'application' => $application,
        ]);
    }

    protected function authorizeOwnership(Request $request, Application $application): void
    {
        abort_unless(
            $application->candidate_id === $request->user()->candidateProfile?->id,
            403,
            'You are not authorized to access this application.'
        );
    }
}