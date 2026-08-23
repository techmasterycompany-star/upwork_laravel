<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'employer';
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'industry'     => ['nullable', 'string', 'max:255'],
            'website'      => ['nullable', 'url', 'max:255'],
        ];
    }
}