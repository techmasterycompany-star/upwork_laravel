<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'job_post_limit',
        'price_monthly',
        'price_yearly',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}