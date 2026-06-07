<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_active',
        'form_schema',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'form_schema' => 'array',
        ];
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}