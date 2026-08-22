<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'plan_id',
        'billing_cycle',
        'status',
        'current_period_start',
        'current_period_end',
    ];

    public function employer()
    {
        return $this->belongsTo(EmployerProfile::class, 'employer_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}