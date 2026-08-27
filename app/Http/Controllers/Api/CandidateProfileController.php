<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CandidateProfileRequest;
use App\Http\Requests\UpdateCandidateResumeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CandidateProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Candidate profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile,
        ]);
    }

    public function update(CandidateProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Candidate profile not found.',
            ], 404);
        }

        $profile->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Candidate profile saved successfully.',
            'profile' => $profile,
        ]);
    }

    public function uploadResume(UpdateCandidateResumeRequest $request): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Candidate profile not found.',
            ], 404);
        }

        if ($profile->resume) {
            Storage::disk('public')->delete($profile->resume);
        }

        $path = $request->file('resume')->store('resumes', 'public');

        $profile->update([
            'resume' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Resume uploaded successfully.',
            'resume_url' => Storage::url($path),
            'profile' => $profile,
        ]);
    }
}