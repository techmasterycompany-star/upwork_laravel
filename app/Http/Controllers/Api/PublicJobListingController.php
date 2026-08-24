<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;

class PublicJobListingController extends Controller
{
    public function index(): JsonResponse
    {
        $jobs = JobListing::query()
            ->where('status', 'approved')
            ->with('category', 'technologies', 'employer')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'jobs' => $jobs,
        ]);
    }

    
    public function show(JobListing $job): JsonResponse
    {
        if ($job->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Job not found.',
            ], 404);
        }

        $job->increment('views_count');

        return response()->json([
            'success' => true,
            'job' => $job->load('category', 'technologies', 'employer'),
        ]);
    }
}