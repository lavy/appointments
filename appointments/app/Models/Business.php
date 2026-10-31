<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'address',
        'timezone',
        'payment_instructions',
        'workday_start',
        'workday_end',
        'slot_minutes',
        'working_days',
        'closed_dates',
        'message_templates',
        'whatsapp_phone_number_id',
        'whatsapp_verify_token',
        'whatsapp_token',
        'whatsapp_app_secret',
        'telegram_bot_token',
        'telegram_secret_token',
        'messenger_page_id',
        'messenger_page_access_token',
        'messenger_verify_token',
        'messenger_app_secret',
    ];

    protected $casts = [
        'working_days' => 'array',
        'closed_dates' => 'array',
        'message_templates' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function conversationStates()
    {
        return $this->hasMany(ConversationState::class);
    }
}
