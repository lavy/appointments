<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pre_reserved',
        'pending_review',
        'confirmed',
        'completed',
        'canceled',
        'expired',
    ];

    protected $fillable = [
        'business_id',
        'customer_name',
        'customer_phone',
        'date',
        'time',
        'status',
        'contact_channel',
        'contact_identifier',
        'language',
        'reminder_sent_at',
        'pre_reserved_until',
        'payment_proof_path',
        'payment_submitted_at',
        'payment_reviewed_by',
        'payment_reviewed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i',
        'reminder_sent_at' => 'datetime',
        'pre_reserved_until' => 'datetime',
        'payment_submitted_at' => 'datetime',
        'payment_reviewed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function paymentReviewer()
    {
        return $this->belongsTo(User::class, 'payment_reviewed_by');
    }
}
