<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectApplicationRequest;
use App\Models\Application;
use App\Models\JobListing;
use App\Services\AuditLogger;
use App\Services\NotificationService;
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

        $oldStatus = $application->status;

        $application->update([
            'status' => 'under_review',
            'reviewed_at' => now(),
        ]);

        AuditLogger::log(
            action: 'application_reviewed',
            modelType: Application::class,
            modelId: $application->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'under_review']
        );

        $this->notifyCandidateOfStatusChange($application, 'under_review');

        return response()->json([
            'success' => true,
            'message' => 'Application marked as under review.',
            'application' => $application,
        ]);
    }


    public function accept(Request $request, Application $application): JsonResponse
    {
        $this->authorizeOwnership($request, $application);

        $oldStatus = $application->status;

        $application->update([
            'status' => 'accepted',
            'reviewed_at' => $application->reviewed_at ?? now(),
        ]);

        AuditLogger::log(
            action: 'application_accepted',
            modelType: Application::class,
            modelId: $application->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'accepted']
        );

        $this->notifyCandidateOfStatusChange($application, 'accepted');

        return response()->json([
            'success' => true,
            'message' => 'Application accepted.',
            'application' => $application,
        ]);
    }

    public function reject(RejectApplicationRequest $request, Application $application): JsonResponse
    {
        $this->authorizeOwnership($request, $application);

        $oldStatus = $application->status;

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('rejection_reason'),
            'reviewed_at' => $application->reviewed_at ?? now(),
        ]);

        AuditLogger::log(
            action: 'application_rejected',
            modelType: Application::class,
            modelId: $application->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'rejected', 'rejection_reason' => $application->rejection_reason]
        );

        $this->notifyCandidateOfStatusChange($application, 'rejected', $application->rejection_reason);

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

    /**
     * Issue #37: notify the candidate whenever their application status changes.
     */
    protected function notifyCandidateOfStatusChange(Application $application, string $status, ?string $reason = null): void
    {
        $application->loadMissing('candidate.user', 'job');

        if (! $application->candidate || ! $application->candidate->user) {
            return;
        }

        $jobTitle = $application->job->title ?? 'the job';

        [$title, $content] = match ($status) {
            'accepted'     => ['Application accepted', "Your application for \"{$jobTitle}\" has been accepted."],
            'rejected'     => ['Application rejected', "Your application for \"{$jobTitle}\" has been rejected." . ($reason ? " Reason: {$reason}" : '')],
            'under_review' => ['Application under review', "Your application for \"{$jobTitle}\" is now under review."],
            default        => ['Application status updated', "Your application for \"{$jobTitle}\" status changed to {$status}."],
        };

        NotificationService::send(
            user: $application->candidate->user,
            type: 'application_status_changed',
            title: $title,
            content: $content,
            data: [
                'application_id' => $application->id,
                'status'         => $status,
            ],
        );
    }
}