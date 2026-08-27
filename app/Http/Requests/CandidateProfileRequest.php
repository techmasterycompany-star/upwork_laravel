<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'candidate';
    }

    public function rules(): array
    {
        return [
            'bio'           => ['nullable', 'string'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'location'      => ['nullable', 'string', 'max:255'],
        ];
    }
}