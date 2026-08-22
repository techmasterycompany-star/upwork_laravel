<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'amount',
        'currency',
        'gateway',
        'gateway_transaction_id',
        'status',
        'paid_at',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}