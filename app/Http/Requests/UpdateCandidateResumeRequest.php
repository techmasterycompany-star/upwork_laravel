<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'candidate';
    }

    public function rules(): array
    {
        return [
            'resume' => ['required', 'file', 'mimes:pdf', 'max:4096'],
        ];
    }
}