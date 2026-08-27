<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchJobsRequest;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;

class JobSearchController extends Controller
{
    public function index(SearchJobsRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = JobListing::query()
            ->where('status', 'approved')
            ->with('category', 'technologies', 'employer');

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if (! empty($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['work_type'])) {
            $query->where('work_type', $filters['work_type']);
        }

        if (! empty($filters['salary_min'])) {
            $query->where('salary_max', '>=', $filters['salary_min']);
        }

        if (! empty($filters['salary_max'])) {
            $query->where('salary_min', '<=', $filters['salary_max']);
        }

        if (! empty($filters['experience_level'])) {
            $query->where('experience_level', $filters['experience_level']);
        }

        $sortColumn = match ($filters['sort_by'] ?? 'date') {
            'salary' => 'salary_max',
            default  => 'created_at',
        };

        $query->orderBy($sortColumn, $filters['sort_direction'] ?? 'desc');

        $jobs = $query->paginate(15);

        return response()->json([
            'success' => true,
            'jobs' => $jobs,
        ]);
    }
}