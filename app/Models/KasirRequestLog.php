<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KasirRequestLog extends Model
{
    use HasFactory;

    protected $table = 'kasir_request_log';

    protected $fillable = [
        'company_id',
        'request_user',
        'transaction_id',
        'provider',
        'action',
        'request_url',
        'request_method',
        'request_headers',
        'request_body',
        'response_status_code',
        'response_headers',
        'response_body',
        'is_success',
        'error_message',
        'request_time',
        'response_time',
    ];

    protected $casts = [
        'request_time' => 'datetime',
        'response_time' => 'datetime',
    ];
}
