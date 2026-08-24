<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchCandidatesRequest;
use App\Models\CandidateProfile;
use Illuminate\Http\JsonResponse;

class CandidateSearchController extends Controller
{
    public function index(SearchCandidatesRequest $request): JsonResponse
    {
        $query = CandidateProfile::query()->with('user', 'skills');

        if ($request->filled('location')) {
            $query->where('location', 'like', '%'.$request->input('location').'%');
        }

        if ($request->filled('skill') || $request->filled('min_experience')) {
            $query->whereHas('skills', function ($skillQuery) use ($request) {
                if ($request->filled('skill')) {
                    $skillQuery->where('name', 'like', '%'.$request->input('skill').'%');
                }

                if ($request->filled('min_experience')) {
                    $skillQuery->where('candidate_skills.years_experience', '>=', $request->input('min_experience'));
                }
            });
        }

        $candidates = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'candidates' => $candidates,
        ]);
    }

    public function show(CandidateProfile $candidate): JsonResponse
    {
        return response()->json([
            'success' => true,
            'candidate' => $candidate->load('user', 'skills'),
        ]);
    }
}