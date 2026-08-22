<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'description',
        'industry',
        'website',
        'company_logo',
        'free_jobs_used',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobs()
    {
        return $this->hasMany(JobListing::class, 'employer_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'employer_id');
    }
}