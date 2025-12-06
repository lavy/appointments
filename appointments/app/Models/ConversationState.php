<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationState extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_phone',
        'current_step',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
