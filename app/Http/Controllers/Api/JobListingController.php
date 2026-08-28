<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobListingRequest;
use App\Http\Requests\UpdateJobListingRequest;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobListingController extends Controller
{
   
    public function index(Request $request): JsonResponse
    {
        $employerProfile = $request->user()->employerProfile;

        $jobs = $employerProfile
            ->jobListings()
            ->with('category', 'technologies')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'jobs' => $jobs,
        ]);
    }

    
    public function show(Request $request, JobListing $job): JsonResponse
    {
        if ($job->employer_id !== $request->user()->employerProfile?->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this job.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'job' => $job->load('category', 'technologies'),
        ]);
    }

    
   public function store(StoreJobListingRequest $request): JsonResponse
{
    $employerProfile = $request->user()->employerProfile;

    if (! $employerProfile) {
        return response()->json([
            'success' => false,
            'message' => 'Please complete your employer profile before posting a job.',
        ], 422);
    }

    $hasActiveSubscription = $employerProfile->subscriptions()
        ->where('status', 'active')
        ->where('current_period_end', '>=', now())
        ->exists();

    if (! $hasActiveSubscription && $employerProfile->free_jobs_used >= 3) {
        return response()->json([
            'success' => false,
            'message' => 'You have used all your free job postings. Please subscribe to a plan to post more jobs.',
        ], 403);
    }

    $job = $employerProfile->jobListings()->create([
        ...collect($request->validated())->except('technologies')->toArray(),
        'status' => 'pending_approval',
    ]);

    if ($request->filled('technologies')) {
        $job->technologies()->sync($request->input('technologies'));
    }

    if (! $hasActiveSubscription) {
        $employerProfile->increment('free_jobs_used');
    }

    return response()->json([
        'success' => true,
        'message' => 'Job listing created and submitted for approval.',
        'job' => $job->load('category', 'technologies'),
    ], 201);
}

    
    public function update(UpdateJobListingRequest $request, JobListing $job): JsonResponse
    {
        $job->update(collect($request->validated())->except('technologies')->toArray());

        if ($request->has('technologies')) {
            $job->technologies()->sync($request->input('technologies', []));
        }

        return response()->json([
            'success' => true,
            'message' => 'Job listing updated successfully.',
            'job' => $job->load('category', 'technologies'),
        ]);
    }

    public function close(Request $request, JobListing $job): JsonResponse
    {
        if ($job->employer_id !== $request->user()->employerProfile?->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to close this job.',
            ], 403);
        }

        $job->update(['status' => 'closed']);

        return response()->json([
            'success' => true,
            'message' => 'Job listing closed successfully.',
            'job' => $job,
        ]);
    }
}