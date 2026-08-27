<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'employer';
    }

    public function rules(): array
    {
        return [
            'category_id'           => ['required', 'exists:categories,id'],
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['required', 'string'],
            'responsibilities'      => ['nullable', 'string'],
            'requirements'          => ['nullable', 'string'],
            'location'              => ['nullable', 'string', 'max:255'],
            'work_type'             => ['required', 'in:remote,onsite,hybrid'],
            'salary_min'            => ['nullable', 'numeric', 'min:0'],
            'salary_max'            => ['nullable', 'numeric', 'gte:salary_min'],
            'experience_level'      => ['nullable', 'string', 'max:255'],
            'application_deadline'  => ['nullable', 'date', 'after:today'],
            'technologies'          => ['nullable', 'array'],
            'technologies.*'        => ['integer', 'exists:technologies,id'],
        ];
    }
}