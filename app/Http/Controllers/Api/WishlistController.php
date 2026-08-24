<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wishlist = $request->user()->candidateProfile
            ->wishlists()
            ->with('job.category', 'job.employer')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'wishlist' => $wishlist,
        ]);
    }

    public function store(Request $request, JobListing $job): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        $exists = $profile->wishlists()->where('job_id', $job->id)->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Job is already in your wishlist.',
            ], 409);
        }

        $item = $profile->wishlists()->create([
            'job_id' => $job->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Job added to wishlist.',
            'wishlist_item' => $item,
        ], 201);
    }

    public function destroy(Request $request, JobListing $job): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        $deleted = $profile->wishlists()->where('job_id', $job->id)->delete();

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Job is not in your wishlist.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Job removed from wishlist.',
        ]);
    }
}