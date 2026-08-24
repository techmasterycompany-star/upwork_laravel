<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCandidateSkillsRequest;
use App\Http\Requests\UpdateCandidateSkillRequest;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CandidateSkillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $skills = $request->user()->candidateProfile->skills()->get();

        return response()->json([
            'success' => true,
            'skills' => $skills,
        ]);
    }

    public function store(StoreCandidateSkillsRequest $request): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        $names = collect(explode(',', $request->validated('skills')))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->map(fn ($name) => Str::title($name))
            ->unique()
            ->values();

        $skillIds = $names->map(function ($name) {
            return Skill::firstOrCreate(['name' => $name])->id;
        });

        foreach ($skillIds as $skillId) {
            if (! $profile->skills()->where('skill_id', $skillId)->exists()) {
                $profile->skills()->attach($skillId, ['years_experience' => 0]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Skills added successfully.',
            'skills' => $profile->skills()->get(),
        ], 201);
    }

    public function update(UpdateCandidateSkillRequest $request, Skill $skill): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        if (! $profile->skills()->where('skill_id', $skill->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This skill is not associated with your profile.',
            ], 404);
        }

        $profile->skills()->updateExistingPivot($skill->id, [
            'years_experience' => $request->validated('years_experience'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Skill updated successfully.',
            'skills' => $profile->skills()->get(),
        ]);
    }

    public function destroy(Request $request, Skill $skill): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        $profile->skills()->detach($skill->id);

        return response()->json([
            'success' => true,
            'message' => 'Skill removed successfully.',
        ]);
    }
}