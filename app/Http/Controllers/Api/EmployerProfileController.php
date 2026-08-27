<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployerProfileRequest;
use App\Http\Requests\UpdateEmployerLogoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployerProfileController extends Controller
{
    /**
     * GET /api/employer/profile
     */
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

    /**
     * PUT /api/employer/profile
     */
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

    /**
     * POST /api/employer/profile/logo
     */
    public function uploadLogo(UpdateEmployerLogoRequest $request): JsonResponse
    {
        $profile = $request->user()->employerProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found.',
            ], 404);
        }

        if ($profile->company_logo) {
            Storage::disk('public')->delete($profile->company_logo);
        }

        $path = $request->file('logo')->store('employer_logos', 'public');

        $profile->update([
            'company_logo' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company logo updated successfully.',
            'logo_url' => Storage::url($path),
            'profile' => $profile,
        ]);
    }
}