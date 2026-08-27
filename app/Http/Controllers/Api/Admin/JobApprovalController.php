<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AuditLogger;

class JobApprovalController extends Controller
{
    public function pending(): JsonResponse
    {
        $jobs = JobListing::with(['employer', 'category'])
            ->where('status', 'pending_approval')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    public function approve(JobListing $job): JsonResponse
{
    if ($job->status !== 'pending_approval') {
        return response()->json([
            'success' => false,
            'message' => 'Only pending jobs can be approved.',
        ], 400);
    }

    $oldStatus = $job->status;

    $job->update([
        'status' => 'approved',
        'rejection_reason' => null,
    ]);

    AuditLogger::log(
        action: 'job_approved',
        modelType: JobListing::class,
        modelId: $job->id,
        oldValues: ['status' => $oldStatus],
        newValues: ['status' => 'approved']
    );

    return response()->json([
        'success' => true,
        'message' => 'Job approved successfully.',
        'data' => $job,
    ]);
}

   public function reject(Request $request, JobListing $job): JsonResponse
{
    if ($job->status !== 'pending_approval') {
        return response()->json([
            'success' => false,
            'message' => 'Only pending jobs can be rejected.',
        ], 400);
    }

    $data = $request->validate([
        'rejection_reason' => 'nullable|string|max:500',
    ]);

    $oldStatus = $job->status;

    $job->update([
        'status' => 'rejected',
        'rejection_reason' => $data['rejection_reason'] ?? null,
    ]);

    AuditLogger::log(
        action: 'job_rejected',
        modelType: JobListing::class,
        modelId: $job->id,
        oldValues: ['status' => $oldStatus],
        newValues: ['status' => 'rejected', 'rejection_reason' => $data['rejection_reason'] ?? null]
    );

    return response()->json([
        'success' => true,
        'message' => 'Job rejected successfully.',
        'data' => $job,
    ]);
}
}