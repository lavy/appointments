<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'completed', 'canceled'];

    protected $fillable = [
        'business_id',
        'customer_name',
        'customer_phone',
        'date',
        'time',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
