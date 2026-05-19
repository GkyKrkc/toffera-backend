<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demand extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'district',
        'min_budget',
        'max_budget',
        'features',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'features'   => 'array',
            'min_budget' => 'decimal:2',
            'max_budget' => 'decimal:2',
        ];
    }

    // ── İlişkiler ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    // ── Scope'lar ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, string $slug)
    {
        return $query->whereHas('category', fn($q) => $q->where('slug', $slug));
    }

    public function scopeByDistrict($query, string $district)
    {
        return $query->where('district', 'like', "%{$district}%");
    }

    public function scopeByBudget($query, ?string $min, ?string $max)
    {
        if ($min) $query->where('max_budget', '>=', $min);
        if ($max) $query->where('min_budget', '<=', $max);
        return $query;
    }

    // ── Yardımcılar ───────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}