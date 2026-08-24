<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $this->user()?->role === 'employer'
            && $job
            && $job->employer_id === $this->user()->employerProfile?->id;
    }

    public function rules(): array
    {
        return [
            'category_id'           => ['sometimes', 'exists:categories,id'],
            'title'                 => ['sometimes', 'string', 'max:255'],
            'description'           => ['sometimes', 'string'],
            'responsibilities'      => ['nullable', 'string'],
            'requirements'          => ['nullable', 'string'],
            'location'              => ['nullable', 'string', 'max:255'],
            'work_type'             => ['sometimes', 'in:remote,onsite,hybrid'],
            'salary_min'            => ['nullable', 'numeric', 'min:0'],
            'salary_max'            => ['nullable', 'numeric', 'gte:salary_min'],
            'experience_level'      => ['nullable', 'string', 'max:255'],
            'application_deadline'  => ['nullable', 'date', 'after:today'],
            'technologies'          => ['nullable', 'array'],
            'technologies.*'        => ['integer', 'exists:technologies,id'],
        ];
    }
}