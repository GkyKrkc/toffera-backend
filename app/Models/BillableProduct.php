<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillableProduct extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'price',
        'credit_amount', 'offer_quota', 'duration_days',
        'categories', 'is_active',
        'portfolio_limit_override', 'unlimited_portfolio',
    ];

    protected $casts = [
        'price'                     => 'decimal:2',
        'categories'                => 'array', // null = tüm kategoriler
        'is_active'                 => 'boolean',
        'unlimited_portfolio'       => 'boolean',
        'portfolio_limit_override'  => 'integer',
    ];
}
