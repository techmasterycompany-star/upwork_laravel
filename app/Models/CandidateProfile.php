<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bio',
        'portfolio_url',
        'resume',
        'phone',
        'location',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills', 'candidate_id', 'skill_id')
            ->withPivot('years_experience')
            ->withTimestamps();
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'candidate_id');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class, 'candidate_id');
    }
}