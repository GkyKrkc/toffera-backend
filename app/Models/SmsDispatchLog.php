<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsDispatchLog extends Model
{
    protected $fillable = [
        'phone',
        'purpose',
        'message',
        'provider',
        'provider_message_id',
        'status',
        'cost',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
    ];
}
