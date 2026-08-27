<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'candidate';
    }

    public function rules(): array
    {
        return [
            
            'skills' => ['required', 'string'],
        ];
    }
}