<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientRequestLog extends Model
{
    use HasFactory;

    protected $table = 'client_request_log';

    protected $fillable = [
        'company_id',
        'transaction_id',
        'provider',
        'event_type',
        'request_url',
        'request_method',
        'request_headers',
        'request_body',
        'signature',
        'signature_valid',
        'response_status_code',
        'response_body',
        'processed_at',
        'is_success',
        'error_message',
        'request_time',
        'response_time',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'request_time' => 'datetime',
        'response_time' => 'datetime',
    ];
}
