<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiChatLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'subscription_status',
        'message',
        'reply',
        'model',
        'is_success',
        'requested_at',
        'responded_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
    ];
}
