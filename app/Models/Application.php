<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'candidate_id',
        'resume',
        'cover_letter',
        'contact_email',
        'contact_phone',
        'status',
        'rejection_reason',
        'reviewed_at',
    ];

    public function job()
    {
        return $this->belongsTo(JobListing::class, 'job_id');
    }

    public function candidate()
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_id');
    }
}