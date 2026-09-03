<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobListing;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    /**
     * Issue #41: generate a draft job description from a title and experience level.
     * This does NOT create a job listing - it just returns a draft for the
     * employer to review/edit before actually calling JobListingController::store().
     */
    public function generateJobDescription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'experience_level' => 'required|string|in:entry,junior,mid,senior,lead',
        ]);

        $prompt = <<<PROMPT
        You are helping an employer draft a job posting.

        Job title: {$data['title']}
        Experience level: {$data['experience_level']}

        Return ONLY a JSON object with exactly these three keys:
        - "description": a 2-3 paragraph overview of the role (plain text)
        - "responsibilities": an array of 5-8 short bullet-point strings
        - "requirements": an array of 5-8 short bullet-point strings

        Do not include markdown formatting, code fences, or any text outside the JSON object.
        PROMPT;

        $result = GeminiService::generateJson($prompt);

        if (! $result || ! isset($result['description'], $result['responsibilities'], $result['requirements'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate a job description draft. Please try again or write it manually.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft generated. Review and edit before publishing.',
            'draft' => [
                'title'            => $data['title'],
                'description'      => $result['description'],
                'responsibilities' => $result['responsibilities'],
                'requirements'     => $result['requirements'],
            ],
        ]);
    }

    /**
     * Issue #42: generate a draft cover letter for a candidate applying to a job.
     * This does NOT submit the application - it just returns a draft for the
     * candidate to review/edit before calling CandidateApplicationController::store().
     */
    public function generateCoverLetter(Request $request, JobListing $job): JsonResponse
    {
        $profile = $request->user()->candidateProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Candidate profile not found.',
            ], 404);
        }

        $skills = $profile->skills()->pluck('name')->implode(', ') ?: 'Not specified';
        $bio    = $profile->bio ?: 'Not provided';

        $prompt = <<<PROMPT
        You are helping a job candidate draft a cover letter for a job application.

        Job title: {$job->title}
        Job description: {$job->description}

        Candidate bio: {$bio}
        Candidate skills: {$skills}

        Return ONLY a JSON object with exactly this key:
        - "cover_letter": a 3-4 paragraph cover letter (plain text), professional in tone,
          tailored to the job description and referencing the candidate's skills/bio where relevant.

        Do not include markdown formatting, code fences, or any text outside the JSON object.
        PROMPT;

        $result = GeminiService::generateJson($prompt);

        if (! $result || ! isset($result['cover_letter'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate a cover letter draft. Please try again or write it manually.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft generated. Review and edit before submitting your application.',
            'draft' => [
                'job_id'        => $job->id,
                'cover_letter'  => $result['cover_letter'],
            ],
        ]);
    }
}