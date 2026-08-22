<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'category_id',
        'title',
        'description',
        'responsibilities',
        'requirements',
        'location',
        'work_type',
        'salary_min',
        'salary_max',
        'experience_level',
        'application_deadline',
        'status',
        'rejection_reason',
        'views_count',
        'applications_count',
    ];

    public function employer()
    {
        return $this->belongsTo(EmployerProfile::class, 'employer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function technologies()
    {
        return $this->belongsToMany(Technology::class, 'job_technologies', 'job_id', 'technology_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'job_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'job_id');
    }

    public function wishlistedBy()
    {
        return $this->hasMany(Wishlist::class, 'job_id');
    }
}