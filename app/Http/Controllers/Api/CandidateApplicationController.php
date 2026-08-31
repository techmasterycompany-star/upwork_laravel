<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\JobListing;
use App\Services\NotificationService;
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

    public function store(StoreApplicationRequest $request, JobListing $job): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        if ($job->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'This job is not open for applications.',
            ], 422);
        }

        $alreadyApplied = $profile->applications()->where('job_id', $job->id)->exists();

        if ($alreadyApplied) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied to this job.',
            ], 409);
        }

        $application = $profile->applications()->create([
            'job_id'         => $job->id,
            'resume'         => $profile->resume,
            'cover_letter'   => $request->validated('cover_letter'),
            'contact_email'  => $request->user()->email,
            'contact_phone'  => $profile->phone,
            'status'         => 'submitted',
        ]);

        $job->increment('applications_count');

        // Issue #37: notify the employer that a new application came in.
        if ($job->employer && $job->employer->user) {
            NotificationService::send(
                user: $job->employer->user,
                type: 'application_received',
                title: 'New application received',
                content: "A candidate applied to your job listing \"{$job->title}\".",
                data: [
                    'application_id' => $application->id,
                    'job_id'         => $job->id,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully.',
            'application' => $application,
        ], 201);
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