<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployerProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployerProfileController extends Controller
{
    // GET /api/employer/profile
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->employerProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile,
        ]);
    }

    // PUT /api/employer/profile
    public function update(EmployerProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->employerProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found.',
            ], 404);
        }

        $profile->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employer profile saved successfully.',
            'profile' => $profile,
        ]);
    }
}