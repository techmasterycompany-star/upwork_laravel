<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public search
    }

    public function rules(): array
    {
        return [
            'keyword'           => ['nullable', 'string', 'max:255'],
            'location'          => ['nullable', 'string', 'max:255'],
            'category_id'       => ['nullable', 'exists:categories,id'],
            'work_type'         => ['nullable', 'in:remote,onsite,hybrid'],
            'salary_min'        => ['nullable', 'numeric', 'min:0'],
            'salary_max'        => ['nullable', 'numeric', 'gte:salary_min'],
            'experience_level'  => ['nullable', 'string', 'max:255'],
            'sort_by'           => ['nullable', 'in:date,salary'],
            'sort_direction'    => ['nullable', 'in:asc,desc'],
        ];
    }
}